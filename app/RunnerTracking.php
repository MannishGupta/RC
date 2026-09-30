<?php
// Version: 1.0
declare(strict_types=1);

/**
 * RunnerTracking — Live Location & Travel History for Office Runners
 *
 * PSR-4 style single-file service (namespace optional for drop-in hosts).
 * Requires PDO MySQL/MariaDB connection. Uses prepared statements exclusively.
 *
 * Actions (via handleRequest):
 *   GET  runners_list          — Office Runner roster + live status
 *   GET  master_email          — system setting master_tracking_email
 *   POST master_email          — update master_tracking_email
 *   POST ping                  — record GPS ping (mobile / device agent)
 *   GET  daily_logs            — breadcrumb trail for runner + date
 *   GET  daily_summaries       — aggregated audit rows
 *   POST rebuild_summary       — recompute one runner-day from logs
 *   GET  export_csv            — Runner Travel Audit Report CSV
 *   GET  export_print          — HTML print-friendly audit report
 */

namespace App;

use PDO;
use PDOException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class RunnerTracking
{
    public const DESIGNATION = 'Office Runner';
    public const OFFLINE_AFTER_SECONDS = 300;   // 5 minutes
    public const MOVING_SPEED_KMH = 1.5;        // below = idle/stationary
    public const IDLE_GAP_SECONDS = 180;        // gap between pings counts idle

    private PDO $pdo;
    private string $tz;

    public function __construct(PDO $pdo, string $timezone = 'Asia/Kolkata')
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo = $pdo;
        $this->tz = $timezone !== '' ? $timezone : 'Asia/Kolkata';
    }

    /**
     * Front-controller style router for ?action=…
     * Call after auth middleware has confirmed admin session.
     */
    public function handleRequest(string $method, array $query, array $body, ?int $actorUserId = null): void
    {
        $action = strtolower(trim((string)($query['action'] ?? $body['action'] ?? '')));
        try {
            switch ($action) {
                case 'runners_list':
                    $this->jsonOk(['runners' => $this->listOfficeRunners()]);
                    break;
                case 'master_email':
                    if (strtoupper($method) === 'POST') {
                        $email = trim((string)($body['email'] ?? ''));
                        $this->jsonOk(['master_tracking_email' => $this->setMasterTrackingEmail($email, $actorUserId)]);
                    } else {
                        $this->jsonOk(['master_tracking_email' => $this->getMasterTrackingEmail()]);
                    }
                    break;
                case 'ping':
                    if (strtoupper($method) !== 'POST') {
                        $this->jsonError(405, 'POST required');
                    }
                    $result = $this->recordPing(
                        (int)($body['runner_id'] ?? 0),
                        (float)($body['latitude'] ?? 0),
                        (float)($body['longitude'] ?? 0),
                        isset($body['speed_kmh']) ? (float)$body['speed_kmh'] : null,
                        isset($body['battery_percentage']) ? (int)$body['battery_percentage'] : null,
                        isset($body['accuracy_m']) ? (float)$body['accuracy_m'] : null,
                        isset($body['heading_deg']) ? (float)$body['heading_deg'] : null,
                        isset($body['recorded_at']) ? (string)$body['recorded_at'] : null
                    );
                    $this->jsonOk($result);
                    break;
                case 'daily_logs':
                    $runnerId = (int)($query['runner_id'] ?? 0);
                    $date = $this->normalizeDate((string)($query['date'] ?? 'today'));
                    $this->jsonOk([
                        'runner_id' => $runnerId,
                        'date' => $date,
                        'points' => $this->getDailyLogs($runnerId, $date),
                        'stats' => $this->computeDayStatsFromLogs($runnerId, $date),
                    ]);
                    break;
                case 'daily_summaries':
                    $runnerId = isset($query['runner_id']) && $query['runner_id'] !== ''
                        ? (int)$query['runner_id'] : null;
                    $from = $this->normalizeDate((string)($query['from'] ?? date('Y-m-d', strtotime('-14 days'))));
                    $to = $this->normalizeDate((string)($query['to'] ?? 'today'));
                    $this->jsonOk([
                        'from' => $from,
                        'to' => $to,
                        'rows' => $this->getDailySummaries($runnerId, $from, $to),
                    ]);
                    break;
                case 'rebuild_summary':
                    if (strtoupper($method) !== 'POST') {
                        $this->jsonError(405, 'POST required');
                    }
                    $runnerId = (int)($body['runner_id'] ?? 0);
                    $date = $this->normalizeDate((string)($body['date'] ?? 'today'));
                    $this->jsonOk($this->rebuildDailySummary($runnerId, $date));
                    break;
                case 'export_csv':
                    $runnerId = isset($query['runner_id']) && $query['runner_id'] !== ''
                        ? (int)$query['runner_id'] : null;
                    $from = $this->normalizeDate((string)($query['from'] ?? date('Y-m-d', strtotime('-30 days'))));
                    $to = $this->normalizeDate((string)($query['to'] ?? 'today'));
                    $this->exportCsv($runnerId, $from, $to);
                    break;
                case 'export_print':
                    $runnerId = isset($query['runner_id']) && $query['runner_id'] !== ''
                        ? (int)$query['runner_id'] : null;
                    $from = $this->normalizeDate((string)($query['from'] ?? date('Y-m-d', strtotime('-30 days'))));
                    $to = $this->normalizeDate((string)($query['to'] ?? 'today'));
                    $this->exportPrintHtml($runnerId, $from, $to);
                    break;
                case 'status_counts':
                    $this->jsonOk($this->statusCounts());
                    break;
                default:
                    $this->jsonError(400, 'Unknown action');
            }
        } catch (Throwable $e) {
            $this->jsonError(500, 'Server error', ['detail' => $e->getMessage()]);
        }
    }

    // ─── Office runners roster ─────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public function listOfficeRunners(): array
    {
        $sql = <<<SQL
SELECT
  u.id,
  u.slug,
  u.name,
  u.email,
  u.phone,
  u.designation,
  u.is_tracking_enabled,
  u.master_share_verified,
  u.last_known_lat,
  u.last_known_lng,
  u.last_location_update,
  u.last_speed_kmh,
  u.last_battery_pct,
  u.is_active
FROM users u
WHERE LOWER(TRIM(u.designation)) = LOWER(:desig)
  AND u.is_active = 1
ORDER BY u.name ASC
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['desig' => self::DESIGNATION]);
        $rows = $stmt->fetchAll();
        $now = time();
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->enrichRunnerRow($r, $now);
        }
        return $out;
    }

    /** @param array<string,mixed> $r */
    private function enrichRunnerRow(array $r, int $now): array
    {
        $lastTs = !empty($r['last_location_update'])
            ? strtotime((string)$r['last_location_update']) : 0;
        $age = $lastTs > 0 ? max(0, $now - $lastTs) : null;
        $speed = $r['last_speed_kmh'] !== null ? (float)$r['last_speed_kmh'] : null;
        $state = 'offline';
        if ($age !== null && $age <= self::OFFLINE_AFTER_SECONDS && !empty($r['is_tracking_enabled'])) {
            $state = ($speed !== null && $speed >= self::MOVING_SPEED_KMH) ? 'moving' : 'idle';
        }
        $initials = $this->initials((string)$r['name']);
        return [
            'id' => (int)$r['id'],
            'slug' => (string)($r['slug'] ?? ''),
            'name' => (string)$r['name'],
            'email' => (string)($r['email'] ?? ''),
            'phone' => (string)($r['phone'] ?? ''),
            'designation' => (string)($r['designation'] ?? ''),
            'is_tracking_enabled' => (int)$r['is_tracking_enabled'] === 1,
            'master_share_verified' => (int)$r['master_share_verified'] === 1,
            'lat' => $r['last_known_lat'] !== null ? (float)$r['last_known_lat'] : null,
            'lng' => $r['last_known_lng'] !== null ? (float)$r['last_known_lng'] : null,
            'last_location_update' => $r['last_location_update'],
            'last_ping_age_seconds' => $age,
            'speed_kmh' => $speed,
            'battery_percentage' => $r['last_battery_pct'] !== null ? (int)$r['last_battery_pct'] : null,
            'state' => $state,
            'initials' => $initials,
            'is_active' => (int)$r['is_active'] === 1,
        ];
    }

    /** @return array{total:int,moving:int,idle:int,offline:int} */
    public function statusCounts(): array
    {
        $runners = $this->listOfficeRunners();
        $c = ['total' => count($runners), 'moving' => 0, 'idle' => 0, 'offline' => 0];
        foreach ($runners as $r) {
            $s = $r['state'];
            if ($s === 'moving') {
                $c['moving']++;
            } elseif ($s === 'idle') {
                $c['idle']++;
            } else {
                $c['offline']++;
            }
        }
        return $c;
    }

    // ─── System setting ────────────────────────────────────────────────────

    public function getMasterTrackingEmail(): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT setting_value FROM system_settings WHERE setting_key = :k LIMIT 1'
        );
        $stmt->execute(['k' => 'master_tracking_email']);
        $v = $stmt->fetchColumn();
        return is_string($v) ? $v : '';
    }

    public function setMasterTrackingEmail(string $email, ?int $actorUserId = null): string
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by)
             VALUES (\'master_tracking_email\', :v, :by)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            'v' => $email,
            'by' => $actorUserId !== null ? (string)$actorUserId : 'admin',
        ]);
        return $email;
    }

    // ─── Ping / breadcrumbs ────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    public function recordPing(
        int $runnerId,
        float $lat,
        float $lng,
        ?float $speedKmh,
        ?int $batteryPct,
        ?float $accuracyM = null,
        ?float $headingDeg = null,
        ?string $recordedAt = null
    ): array {
        if ($runnerId <= 0) {
            throw new \InvalidArgumentException('runner_id required');
        }
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            throw new \InvalidArgumentException('Invalid coordinates');
        }
        if ($batteryPct !== null) {
            $batteryPct = max(0, min(255, $batteryPct));
        }
        if ($speedKmh !== null && $speedKmh < 0) {
            $speedKmh = 0.0;
        }

        $this->assertOfficeRunner($runnerId);

        $tz = new DateTimeZone($this->tz);
        if ($recordedAt !== null && $recordedAt !== '') {
            try {
                $dt = new DateTimeImmutable($recordedAt, $tz);
            } catch (Throwable $e) {
                $dt = new DateTimeImmutable('now', $tz);
            }
        } else {
            $dt = new DateTimeImmutable('now', $tz);
        }
        $recordedSql = $dt->format('Y-m-d H:i:s');
        $summaryDate = $dt->format('Y-m-d');

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO runner_location_logs
                  (runner_id, latitude, longitude, speed_kmh, battery_percentage, accuracy_m, heading_deg, recorded_at)
                 VALUES
                  (:rid, :lat, :lng, :spd, :bat, :acc, :hdg, :at)'
            );
            $ins->execute([
                'rid' => $runnerId,
                'lat' => round($lat, 8),
                'lng' => round($lng, 8),
                'spd' => $speedKmh,
                'bat' => $batteryPct,
                'acc' => $accuracyM,
                'hdg' => $headingDeg,
                'at' => $recordedSql,
            ]);
            $logId = (int)$this->pdo->lastInsertId();

            $upd = $this->pdo->prepare(
                'UPDATE users SET
                   last_known_lat = :lat,
                   last_known_lng = :lng,
                   last_location_update = :at,
                   last_speed_kmh = :spd,
                   last_battery_pct = :bat,
                   is_tracking_enabled = 1
                 WHERE id = :id'
            );
            $upd->execute([
                'lat' => round($lat, 8),
                'lng' => round($lng, 8),
                'at' => $recordedSql,
                'spd' => $speedKmh,
                'bat' => $batteryPct,
                'id' => $runnerId,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // Best-effort daily rollup (non-fatal if it fails)
        try {
            $this->rebuildDailySummary($runnerId, $summaryDate);
        } catch (Throwable $e) {
            // swallow — ping already stored
        }

        return [
            'log_id' => $logId,
            'runner_id' => $runnerId,
            'recorded_at' => $recordedSql,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function assertOfficeRunner(int $runnerId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM users
             WHERE id = :id AND LOWER(TRIM(designation)) = LOWER(:desig) AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $runnerId, 'desig' => self::DESIGNATION]);
        if (!$stmt->fetchColumn()) {
            throw new \InvalidArgumentException('Not an active Office Runner');
        }
    }

    // ─── Daily logs & stats ────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    public function getDailyLogs(int $runnerId, string $date): array
    {
        if ($runnerId <= 0) {
            return [];
        }
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';
        $stmt = $this->pdo->prepare(
            'SELECT id, latitude, longitude, speed_kmh, battery_percentage, accuracy_m, heading_deg, recorded_at
             FROM runner_location_logs
             WHERE runner_id = :rid AND recorded_at BETWEEN :a AND :b
             ORDER BY recorded_at ASC, id ASC'
        );
        $stmt->execute(['rid' => $runnerId, 'a' => $start, 'b' => $end]);
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'lat' => (float)$r['latitude'],
                'lng' => (float)$r['longitude'],
                'speed_kmh' => $r['speed_kmh'] !== null ? (float)$r['speed_kmh'] : null,
                'battery_percentage' => $r['battery_percentage'] !== null ? (int)$r['battery_percentage'] : null,
                'accuracy_m' => $r['accuracy_m'] !== null ? (float)$r['accuracy_m'] : null,
                'heading_deg' => $r['heading_deg'] !== null ? (float)$r['heading_deg'] : null,
                'recorded_at' => (string)$r['recorded_at'],
            ];
        }
        return $out;
    }

    /**
     * Haversine + active/idle classification from ordered points.
     * @return array{total_distance_km:float,total_active_seconds:int,total_idle_seconds:int,start_time:?string,end_time:?string,point_count:int,route_polyline:string}
     */
    public function computeDayStatsFromLogs(int $runnerId, string $date): array
    {
        $points = $this->getDailyLogs($runnerId, $date);
        $n = count($points);
        if ($n === 0) {
            return [
                'total_distance_km' => 0.0,
                'total_active_seconds' => 0,
                'total_idle_seconds' => 0,
                'start_time' => null,
                'end_time' => null,
                'point_count' => 0,
                'route_polyline' => '',
            ];
        }

        $distanceM = 0.0;
        $activeSec = 0;
        $idleSec = 0;
        for ($i = 1; $i < $n; $i++) {
            $prev = $points[$i - 1];
            $cur = $points[$i];
            $segM = $this->haversineMeters($prev['lat'], $prev['lng'], $cur['lat'], $cur['lng']);
            $t0 = strtotime($prev['recorded_at']);
            $t1 = strtotime($cur['recorded_at']);
            $dt = max(0, $t1 - $t0);
            $distanceM += $segM;
            $spd = $cur['speed_kmh'];
            $moving = ($spd !== null && $spd >= self::MOVING_SPEED_KMH)
                || ($dt > 0 && ($segM / max($dt, 1)) * 3.6 >= self::MOVING_SPEED_KMH);
            if ($dt > self::IDLE_GAP_SECONDS && !$moving) {
                $idleSec += $dt;
            } elseif ($moving) {
                $activeSec += $dt;
            } else {
                $idleSec += $dt;
            }
        }

        $coords = [];
        foreach ($points as $p) {
            $coords[] = [(float)$p['lat'], (float)$p['lng']];
        }

        return [
            'total_distance_km' => round($distanceM / 1000.0, 3),
            'total_active_seconds' => $activeSec,
            'total_idle_seconds' => $idleSec,
            'start_time' => $points[0]['recorded_at'],
            'end_time' => $points[$n - 1]['recorded_at'],
            'point_count' => $n,
            'route_polyline' => $this->encodePolyline($coords),
        ];
    }

    /** @return array<string,mixed> */
    public function rebuildDailySummary(int $runnerId, string $date): array
    {
        if ($runnerId <= 0) {
            throw new \InvalidArgumentException('runner_id required');
        }
        $stats = $this->computeDayStatsFromLogs($runnerId, $date);
        $stmt = $this->pdo->prepare(
            'INSERT INTO runner_daily_summaries
              (runner_id, summary_date, total_distance_km, total_active_seconds, total_idle_seconds,
               start_time, end_time, point_count, route_polyline)
             VALUES
              (:rid, :d, :dist, :act, :idle, :st, :en, :pc, :poly)
             ON DUPLICATE KEY UPDATE
              total_distance_km = VALUES(total_distance_km),
              total_active_seconds = VALUES(total_active_seconds),
              total_idle_seconds = VALUES(total_idle_seconds),
              start_time = VALUES(start_time),
              end_time = VALUES(end_time),
              point_count = VALUES(point_count),
              route_polyline = VALUES(route_polyline)'
        );
        $stmt->execute([
            'rid' => $runnerId,
            'd' => $date,
            'dist' => $stats['total_distance_km'],
            'act' => $stats['total_active_seconds'],
            'idle' => $stats['total_idle_seconds'],
            'st' => $stats['start_time'],
            'en' => $stats['end_time'],
            'pc' => $stats['point_count'],
            'poly' => $stats['route_polyline'],
        ]);
        return array_merge(['runner_id' => $runnerId, 'summary_date' => $date], $stats);
    }

    /** @return list<array<string,mixed>> */
    public function getDailySummaries(?int $runnerId, string $from, string $to): array
    {
        $sql = 'SELECT s.*, u.name AS runner_name, u.slug AS runner_slug
                FROM runner_daily_summaries s
                INNER JOIN users u ON u.id = s.runner_id
                WHERE s.summary_date BETWEEN :a AND :b
                  AND LOWER(TRIM(u.designation)) = LOWER(:desig)';
        $params = ['a' => $from, 'b' => $to, 'desig' => self::DESIGNATION];
        if ($runnerId !== null && $runnerId > 0) {
            $sql .= ' AND s.runner_id = :rid';
            $params['rid'] = $runnerId;
        }
        $sql .= ' ORDER BY s.summary_date DESC, u.name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'runner_id' => (int)$r['runner_id'],
                'runner_name' => (string)$r['runner_name'],
                'runner_slug' => (string)($r['runner_slug'] ?? ''),
                'summary_date' => (string)$r['summary_date'],
                'total_distance_km' => (float)$r['total_distance_km'],
                'total_active_seconds' => (int)$r['total_active_seconds'],
                'total_idle_seconds' => (int)$r['total_idle_seconds'],
                'active_hours' => round(((int)$r['total_active_seconds']) / 3600, 2),
                'idle_hours' => round(((int)$r['total_idle_seconds']) / 3600, 2),
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'point_count' => (int)$r['point_count'],
                'route_polyline' => (string)($r['route_polyline'] ?? ''),
            ];
        }
        return $out;
    }

    // ─── Export ────────────────────────────────────────────────────────────

    public function exportCsv(?int $runnerId, string $from, string $to): void
    {
        $rows = $this->getDailySummaries($runnerId, $from, $to);
        $filename = 'runner_travel_audit_' . $from . '_to_' . $to . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('Unable to open output stream');
        }
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, [
            'Date', 'Runner ID', 'Runner Name', 'Start Time', 'End Time',
            'Distance (km)', 'Active Hours', 'Idle Hours', 'Active Seconds', 'Idle Seconds', 'Points',
        ]);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['summary_date'],
                $r['runner_id'],
                $r['runner_name'],
                $r['start_time'] ?? '',
                $r['end_time'] ?? '',
                number_format((float)$r['total_distance_km'], 3, '.', ''),
                number_format((float)$r['active_hours'], 2, '.', ''),
                number_format((float)$r['idle_hours'], 2, '.', ''),
                $r['total_active_seconds'],
                $r['total_idle_seconds'],
                $r['point_count'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function exportPrintHtml(?int $runnerId, string $from, string $to): void
    {
        $rows = $this->getDailySummaries($runnerId, $from, $to);
        $master = htmlspecialchars($this->getMasterTrackingEmail(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fromH = htmlspecialchars($from, ENT_QUOTES, 'UTF-8');
        $toH = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Runner Travel Audit Report</title>';
        echo '<style>
          body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#0f172a;margin:24px;font-size:13px}
          h1{font-size:18px;margin:0 0 4px} .meta{color:#64748b;margin-bottom:16px}
          table{width:100%;border-collapse:collapse} th,td{border:1px solid #e2e8f0;padding:8px 10px;text-align:left}
          th{background:#f1f5f9;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
          tr:nth-child(even) td{background:#f8fafc}
          @media print{body{margin:12px} .no-print{display:none}}
        </style></head><body>';
        echo '<button class="no-print" onclick="window.print()" style="margin-bottom:12px;padding:8px 14px;border-radius:8px;border:0;background:#0f172a;color:#fff;font-weight:700;cursor:pointer">Print</button>';
        echo '<h1>Runner Travel Audit Report</h1>';
        echo '<div class="meta">Period: ' . $fromH . ' → ' . $toH . ' · Master tracking: ' . $master . ' · Generated ' . date('Y-m-d H:i') . '</div>';
        echo '<table><thead><tr>';
        echo '<th>Date</th><th>Runner</th><th>Start</th><th>End</th><th>Distance (km)</th><th>Active (h)</th><th>Idle (h)</th><th>Points</th>';
        echo '</tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="8">No summary rows for this range.</td></tr>';
        }
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string)$r['summary_date'], ENT_QUOTES) . '</td>';
            echo '<td>' . htmlspecialchars((string)$r['runner_name'], ENT_QUOTES) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['start_time'] ?? ''), ENT_QUOTES) . '</td>';
            echo '<td>' . htmlspecialchars((string)($r['end_time'] ?? ''), ENT_QUOTES) . '</td>';
            echo '<td>' . number_format((float)$r['total_distance_km'], 3) . '</td>';
            echo '<td>' . number_format((float)$r['active_hours'], 2) . '</td>';
            echo '<td>' . number_format((float)$r['idle_hours'], 2) . '</td>';
            echo '<td>' . (int)$r['point_count'] . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || strtolower($raw) === 'today') {
            return (new DateTimeImmutable('now', new DateTimeZone($this->tz)))->format('Y-m-d');
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw, new DateTimeZone($this->tz));
        if (!$dt) {
            throw new \InvalidArgumentException('Invalid date; use YYYY-MM-DD');
        }
        return $dt->format('Y-m-d');
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $a = '';
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $a .= mb_strtoupper(mb_substr($p, 0, 1));
            if (mb_strlen($a) >= 2) {
                break;
            }
        }
        return $a !== '' ? $a : '?';
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000.0;
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lon2 - $lon1);
        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
        return 2 * $R * asin(min(1.0, sqrt($a)));
    }

    /**
     * Google-encoded polyline (precision 5).
     * @param list<array{0:float,1:float}> $coords [lat, lng]
     */
    private function encodePolyline(array $coords): string
    {
        $lastLat = 0;
        $lastLng = 0;
        $result = '';
        foreach ($coords as $c) {
            $lat = (int)round($c[0] * 1e5);
            $lng = (int)round($c[1] * 1e5);
            $result .= $this->encodeSigned($lat - $lastLat);
            $result .= $this->encodeSigned($lng - $lastLng);
            $lastLat = $lat;
            $lastLng = $lng;
        }
        return $result;
    }

    private function encodeSigned(int $num): string
    {
        $sgn = $num < 0 ? ~($num << 1) : ($num << 1);
        $out = '';
        while ($sgn >= 0x20) {
            $out .= chr((0x20 | ($sgn & 0x1f)) + 63);
            $sgn >>= 5;
        }
        $out .= chr($sgn + 63);
        return $out;
    }

    /** @param array<string,mixed> $extra */
    private function jsonOk(array $extra = []): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode(array_merge(['ok' => true], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param array<string,mixed> $extra */
    private function jsonError(int $code, string $message, array $extra = []): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode(array_merge(['ok' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Factory from DSN env or explicit credentials.
     */
    public static function fromEnv(): self
    {
        $dsn = getenv('RUNNER_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=arthsathi;charset=utf8mb4';
        $user = getenv('RUNNER_DB_USER') ?: 'root';
        $pass = getenv('RUNNER_DB_PASS') !== false ? (string)getenv('RUNNER_DB_PASS') : '';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $tz = getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
        return new self($pdo, $tz);
    }
}

// ---------------------------------------------------------------------------
// Optional CLI / direct endpoint bootstrap when this file is the front controller
// e.g. /api/runner_tracking.php including this file after session auth.
// ---------------------------------------------------------------------------
if (php_sapi_name() !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    // Direct hit on this file — expect session already started by host router if any
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $query = $_GET;
    $body = $_POST;
    if ($method === 'POST' && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '[]', true);
        if (is_array($json)) {
            $body = $json;
        }
    }
    $actor = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    try {
        $svc = RunnerTracking::fromEnv();
        $svc->handleRequest($method, $query, $body, $actor);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}
