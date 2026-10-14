<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) { exit; }

class Redaction
{
     
    public static function member(array $member, bool $isVisitor, bool $isPublicCard = false): array
    {
        $sharePublic = !empty($member['share_public']) || !empty($member['public_contact']);
        if (!$isVisitor && !$isPublicCard) {
            return $member;
        }
         
        $sensitive = ['phone', 'email', 'mobile', 'whatsapp', 'address', 'dob', 'tob', 'time_of_birth', 'place_of_birth', 'gotra', 'blood_group'];
        if ($isVisitor || ($isPublicCard && !$sharePublic)) {
            foreach ($sensitive as $k) {
                if (isset($member[$k]) && $member[$k] !== '') {
                    if ($isPublicCard && in_array($k, ['phone', 'email', 'mobile', 'whatsapp'], true) && $sharePublic) {
                        continue;
                    }
                    if ($isPublicCard && in_array($k, ['phone', 'email'], true)) {
                         
                         
                        continue;
                    }
                    if ($isVisitor && !$sharePublic) {
                        $member[$k] = '';
                        $member['_' . $k . '_redacted'] = true;
                    }
                }
            }
             
            foreach (['slug_locked', 'hierarchy_rank', 'rank', 'id', 'created_at', 'updated_at'] as $k) {
                unset($member[$k]);
            }
        }
        return $member;
    }

    public static function isVisitorSession(): bool
    {
        $u = (string) ($_SESSION['user'] ?? '');
        return $u === 'public' || $u === 'visitor';
    }
}
