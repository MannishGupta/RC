<?php
// Version: 3.1.1
declare(strict_types=1);

/**
 * Versioned legal/DPDP notices — Eighth Schedule language aware.
 */
final class NoticeStore
{
    public static function baseDir(): string
    {
        $root = defined('DATA_PATH') ? rtrim((string) DATA_PATH, '/\\') : (defined('BASE_PATH') ? BASE_PATH . '/data' : __DIR__);
        $dir = $root . '/compliance/notices';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * @return array{version:string,published_at:string,lang:string,title:string,body:string}
     */
    public static function get(string $type, string $lang = 'en'): array
    {
        if (!class_exists('EighthSchedule') && is_file(dirname(__DIR__) . '/I18n/EighthSchedule.php')) {
            require_once dirname(__DIR__) . '/I18n/EighthSchedule.php';
        }
        $type = preg_replace('/[^a-z0-9_\-]/', '', strtolower($type)) ?: 'privacy';
        $lang = class_exists('EighthSchedule') ? EighthSchedule::normalize($lang) : 'en';

        foreach ([$lang, 'hi', 'en'] as $try) {
            $path = self::baseDir() . '/' . $type . '.' . $try . '.json';
            if (!is_file($path)) {
                continue;
            }
            $j = json_decode((string) file_get_contents($path), true);
            if (is_array($j) && !empty($j['body'])) {
                return [
                    'version' => (string) ($j['version'] ?? '1'),
                    'published_at' => (string) ($j['published_at'] ?? ''),
                    'lang' => $try,
                    'title' => (string) ($j['title'] ?? ucfirst($type)),
                    'body' => (string) $j['body'],
                ];
            }
        }
        return self::fallback($type, $lang);
    }

    /**
     * @return array{version:string,published_at:string,lang:string,title:string,body:string}
     */
    private static function fallback(string $type, string $lang): array
    {
        $company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
        $name = trim((string) ($company['name'] ?? 'Organization'));
        $gro = trim((string) ($company['dpdp']['grievance_officer'] ?? $company['grievance_officer'] ?? ''));
        $email = trim((string) ($company['dpdp']['grievance_email'] ?? $company['email'] ?? ''));
        $addr = trim((string) ($company['address'] ?? $company['dpdp']['address'] ?? ''));

        $titles = [
            'en' => ['privacy' => 'Privacy & DPDP Notice', 'terms' => 'Terms of Service', 'cookies' => 'Cookie Policy'],
            'hi' => ['privacy' => 'गोपनीयता एवं डीपीडीपी सूचना', 'terms' => 'सेवा की शर्तें', 'cookies' => 'कुकी नीति'],
            'bn' => ['privacy' => 'গোপনীয়তা ও ডিপিডিপি বিজ্ঞপ্তি', 'terms' => 'সেবার শর্তাবলী', 'cookies' => 'কুকি নীতি'],
            'ta' => ['privacy' => 'தனியுரிமை மற்றும் DPDP அறிவிப்பு', 'terms' => 'சேவை விதிமுறைகள்', 'cookies' => 'குக்கீ கொள்கை'],
            'te' => ['privacy' => 'గోప్యత & DPDP నోటీసు', 'terms' => 'సేవా నిబంధనలు', 'cookies' => 'కుకీ విధానం'],
            'mr' => ['privacy' => 'गोपनीयता आणि DPDP सूचना', 'terms' => 'सेवा अटी', 'cookies' => 'कुकी धोरण'],
            'gu' => ['privacy' => 'ગોપનીયતા અને DPDP સૂચના', 'terms' => 'સેવાની શરતો', 'cookies' => 'કુકી નીતિ'],
            'kn' => ['privacy' => 'ಗೌಪ್ಯತೆ ಮತ್ತು DPDP ಸೂಚನೆ', 'terms' => 'ಸೇವಾ ನಿಯಮಗಳು', 'cookies' => 'ಕುಕಿ ನೀತಿ'],
            'ml' => ['privacy' => 'സ്വകാര്യത & DPDP അറിയിപ്പ്', 'terms' => 'സേവന നിബന്ധനകൾ', 'cookies' => 'കുക്കി നയം'],
            'pa' => ['privacy' => 'ਪਰਾਈਵੇਸੀ ਅਤੇ DPDP ਨੋਟਿਸ', 'terms' => 'ਸੇਵਾ ਦੀਆਂ ਸ਼ਰਤਾਂ', 'cookies' => 'ਕੁਕੀ ਨੀਤੀ'],
            'or' => ['privacy' => 'ଗୋପନୀୟତା ଏବଂ DPDP ସୂଚନା', 'terms' => 'ସେବା ସର୍ତ୍ତ', 'cookies' => 'କୁକି ନୀତି'],
            'ur' => ['privacy' => 'رازداری اور DPDP نوٹس', 'terms' => 'شرائط خدمات', 'cookies' => 'کوکی پالیسی'],
        ];

        $titleMap = $titles[$lang] ?? $titles['en'];
        $title = $titleMap[$type] ?? $titleMap['privacy'];

        // Legal body: localized lead sentence where available, else English (fiduciary facts stay accurate)
        $leads = [
            'hi' => "{$name} वैध व्यावसायिक उद्देश्यों के लिए व्यक्तिगत डेटा संसाधित करता है। डीपीडीपी अधिनियम 2023 के अंतर्गत पहुँच, सुधार, मिटाने और नामित करने के अधिकार उपलब्ध हैं।",
            'bn' => "{$name} বৈধ ব্যবসায়িক উদ্দেশ্যে ব্যক্তিগত ডেটা প্রক্রিয়া করে। DPDP আইন ২০২৩ অনুযায়ী আপনার অধিকার রয়েছে।",
            'ta' => "{$name} முறையான வணிக நோக்கங்களுக்காக தனிப்பட்ட தரவை செயலாக்குகிறது. DPDP சட்டம் 2023 கீழ் உங்கள் உரிமைகள் உள்ளன.",
            'te' => "{$name} చట్టబద్ధమైన వ్యాపార ప్రయోజనాల కోసం వ్యక్తిగత డేటాను ప్రాసెస్ చేస్తుంది. DPDP చట్టం 2023 కింద మీ హక్కులు ఉన్నాయి.",
            'mr' => "{$name} कायदेशीर व्यावसायिक उद्देशांसाठी वैयक्तिक डेटा प्रक्रिया करते. DPDP कायदा 2023 अंतर्गत तुमचे हक्क आहेत.",
            'gu' => "{$name} કાયદેસર વ્યવસાયિક હેતુઓ માટે વ્યક્તિગત ડેટા પ્રક્રિયા કરે છે. DPDP અધિનિયમ 2023 હેઠળ તમારા અધિકારો છે.",
            'kn' => "{$name} ಕಾನೂನುಬದ್ಧ ವ್ಯಾಪಾರ ಉದ್ದೇಶಗಳಿಗಾಗಿ ವೈಯಕ್ತಿಕ ಡೇಟಾವನ್ನು ಸಂಸ್ಕರಿಸುತ್ತದೆ. DPDP ಕಾಯಿದೆ 2023 ಅಡಿಯಲ್ಲಿ ನಿಮ್ಮ ಹಕ್ಕುಗಳಿವೆ.",
            'ml' => "{$name} നിയമാനുസൃത ബിസിനസ് ആവശ്യങ്ങൾക്കായി വ്യക്തിഗത ഡാറ്റ പ്രോസസ്സ് ചെയ്യുന്നു. DPDP നിയമം 2023 പ്രകാരം അവകാശങ്ങളുണ്ട്.",
            'pa' => "{$name} ਕਾਨੂੰਨੀ ਕਾਰੋਬਾਰੀ ਮਕਸਦਾਂ ਲਈ ਨਿੱਜੀ ਡਾਟਾ ਪ੍ਰੋਸੈਸ ਕਰਦਾ ਹੈ। DPDP ਐਕਟ 2023 ਤਹਿਤ ਤੁਹਾਡੇ ਅਧਿਕਾਰ ਹਨ।",
            'or' => "{$name} ଆଇନଗତ ବ୍ୟବସାୟିକ ଉଦ୍ଦେଶ୍ୟରେ ବ୍ୟକ୍ତିଗତ ତଥ୍ୟ ପ୍ରକ୍ରିୟା କରେ। DPDP ଆଇନ 2023 ଅନୁଯାୟୀ ଆପଣଙ୍କ ଅଧିକାର ଅଛି।",
            'ur' => "{$name} جائز کاروباری مقاصد کے لیے ذاتی ڈیٹا پر کارروائی کرتا ہے۔ DPDP ایکٹ 2023 کے تحت آپ کے حقوق ہیں۔",
        ];

        $body = $leads[$lang] ?? (
            $name . ' processes personal data for legitimate organisational purposes (directory operations, access control, and service delivery). '
            . 'Under the Digital Personal Data Protection Act, 2023, you may request access, correction, erasure, and nomination. '
        );
        if ($gro !== '') {
            $body .= ' Grievance Officer: ' . $gro . '.';
        }
        if ($email !== '') {
            $body .= ' Contact: ' . $email . '.';
        }
        if ($addr !== '') {
            $body .= ' Address: ' . $addr . '.';
        }
        $body .= ' Non-essential cookies are not used until you consent. You may withdraw consent at any time from Privacy settings.';

        return [
            'version' => '1.1.0',
            'published_at' => defined('APP_VERSION_DATE') ? (string) APP_VERSION_DATE : date('Y-m-d'),
            'lang' => $lang,
            'title' => $title,
            'body' => $body,
        ];
    }

    public static function ensureDefaults(): void
    {
        if (!class_exists('EighthSchedule') && is_file(dirname(__DIR__) . '/I18n/EighthSchedule.php')) {
            require_once dirname(__DIR__) . '/I18n/EighthSchedule.php';
        }
        $langs = class_exists('EighthSchedule') ? ['en', 'hi', 'bn', 'ta', 'te', 'mr', 'gu', 'kn', 'ml', 'pa', 'or', 'ur'] : ['en', 'hi'];
        foreach (['privacy', 'terms', 'cookies'] as $type) {
            foreach ($langs as $lang) {
                $path = self::baseDir() . '/' . $type . '.' . $lang . '.json';
                if (is_file($path)) {
                    continue;
                }
                $n = self::fallback($type, $lang);
                @file_put_contents($path, json_encode($n, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
        }
    }
}
