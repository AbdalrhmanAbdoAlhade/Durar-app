<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected static string $endpoint = 'https://bakewats.zh-innovation.tech/api/sessions/durar/send';

    /**
     * إرسال رسالة واتساب مع تغيير قوي في الشكل + تأخير عشوائي
     */
    public static function sendMessage(string $to, string $message, bool $vary = true): array
    {
        $cleanPhone = self::cleanPhone($to);

        if (!$cleanPhone) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف غير صالح',
            ];
        }

        // تأخير عشوائي بسيط (من 0.3 إلى 1.2 ثانية)
        usleep(rand(300000, 1200000));

        $finalMessage = $vary ? self::varyMessageStrong($message) : $message;

        try {
            $response = Http::timeout(20)->post(self::$endpoint, [
                'to'      => $cleanPhone,
                'message' => $finalMessage,
            ]);

            $body = $response->body();

            if ($response->successful()) {
                return [
                    'success'         => true,
                    'message'         => 'تم الإرسال بنجاح',
                    'response'        => $body,
                    'varied_message'  => $finalMessage,
                ];
            }

            Log::warning('WhatsApp send failed', [
                'phone'  => $cleanPhone,
                'status' => $response->status(),
                'body'   => $body,
            ]);

            return [
                'success'  => false,
                'message'  => 'فشل الإرسال',
                'response' => $body,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Exception', [
                'phone' => $cleanPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'خطأ في الاتصال: ' . $e->getMessage(),
            ];
        }
    }

   
 /**
 * تنظيف رقم الهاتف
 */
protected static function cleanPhone(?string $phone): ?string
{
    if (!$phone) {
        return null;
    }

    // إزالة أي حاجة غير الأرقام
    $clean = preg_replace('/[^0-9]/', '', $phone);

    // إزالة 00 من البداية (رمز الاتصال الدولي)
    if (str_starts_with($clean, '00')) {
        $clean = substr($clean, 2);
    }

    // لو الرقم بيبدأ بـ 0 (صيغة محلية) → حوّله لصيغة دولية مصر
    if (str_starts_with($clean, '0')) {
        $clean = '20' . substr($clean, 1);
    }

    // إصلاح حالة: 200xxxxxxxxx (فيها صفر زيادة بعد كود الدولة)
    // مثال: 2001287111405 → 201287111405
    if (str_starts_with($clean, '200') && strlen($clean) >= 13) {
        $clean = '20' . substr($clean, 3);
    }

    // التأكد من طول الرقم
    if (strlen($clean) < 10 || strlen($clean) > 15) {
        return null;
    }

    return $clean;
}
    /**
     * تغيير قوي لشكل الرسالة
     */
    protected static function varyMessageStrong(string $message): string
    {
        // 1. تقسيم الرسالة إلى جمل
        $sentences = preg_split('/(?<=[.!؟\n])\s+/u', trim($message), -1, PREG_SPLIT_NO_EMPTY);

        // 2. جمل ترحيبية عشوائية (تُضاف في البداية أحياناً)
        $greetings = [
            "مرحباً بك 👋",
            "أهلاً وسهلاً ✨",
            "يسعدنا تواصلك معنا 💎",
            "تحية طيبة 🌟",
            "مرحباً بيك في عائلة رُقي ❤️",
            "نورتنا 🙏",
            "", // احتمال ما نضيفش حاجة
        ];

        // 3. جمل ختامية عشوائية
        $closings = [
            "مع خالص التحية ❤️",
            "نتمنى لك يوماً سعيداً ✨",
            "في انتظارك دائماً 💎",
            "شكراً لثقتك بنا 🙏",
            "فريق رُقي يتمنى لك التوفيق 🌟",
            "يسعدنا خدمتك دائماً 👋",
            "", // احتمال ما نضيفش
        ];

        // 4. مرادفات بسيطة لبعض الكلمات الشائعة
        $replacements = [
            'مرحباً'   => ['مرحباً', 'أهلاً', 'أهلاً بك', 'مرحبا بيك'],
            'نشكر'     => ['نشكر', 'نقدر', 'نشكر لكم', 'ممتنين'],
            'يسعدنا'   => ['يسعدنا', 'يسعدنا جداً', 'يسعدنا انضمامك'],
            'نرجو'     => ['نرجو', 'نأمل', 'نرجو منكم'],
            'سيتم'     => ['سيتم', 'هيتم', 'سوف يتم'],
            'خلال'     => ['خلال', 'في غضون', 'في مدة'],
        ];

        // تطبيق المرادفات عشوائياً
        foreach ($replacements as $word => $alternatives) {
            if (str_contains($message, $word) && rand(0, 1)) {
                $message = str_replace($word, $alternatives[array_rand($alternatives)], $message);
            }
        }

        // إعادة تقسيم بعد التعديل
        $sentences = preg_split('/(?<=[.!؟\n])\s+/u', trim($message), -1, PREG_SPLIT_NO_EMPTY);

        // 5. احتمال تغيير ترتيب بعض الجمل (بحذر عشان المعنى ميبوظش)
        if (count($sentences) > 3 && rand(0, 1)) {
            // نقل جملة من الوسط للنهاية أو العكس بشكل محدود
            $mid = (int) (count($sentences) / 2);
            $temp = $sentences[$mid];
            $sentences[$mid] = $sentences[count($sentences) - 1];
            $sentences[count($sentences) - 1] = $temp;
        }

        $body = implode("\n", $sentences);

        // 6. إضافة ترحيب في البداية (احتمال 60%)
        $greeting = $greetings[array_rand($greetings)];
        if ($greeting && rand(1, 10) <= 6) {
            $body = $greeting . "\n\n" . $body;
        }

        // 7. إضافة ختام في النهاية (احتمال 50%)
        $closing = $closings[array_rand($closings)];
        if ($closing && rand(1, 10) <= 5) {
            $body .= "\n\n" . $closing;
        }

        // 8. تعديلات شكلية إضافية
        $shapeVariations = [
            // مسافات إضافية في النهاية
            fn($msg) => $msg . str_repeat(' ', rand(1, 5)),

            // سطور فارغة عشوائية
            fn($msg) => $msg . str_repeat("\n", rand(1, 3)),

            // رموز تعبيرية
            function ($msg) {
                $emojis = ['✨', '💎', '🌟', '✅', '📌', '🔔', '💫', '❤️', '🙏', '👋', '🎉'];
                $emoji = $emojis[array_rand($emojis)];
                return rand(0, 1) ? $emoji . ' ' . $msg : $msg . ' ' . $emoji;
            },

            // فواصل زخرفية
            function ($msg) {
                $seps = ["\n---\n", "\n• • •\n", "\n————\n", "\n✦ ✦ ✦\n"];
                return $msg . $seps[array_rand($seps)];
            },

            // Zero-width spaces (غير مرئية)
            fn($msg) => $msg . str_repeat("\u{200B}", rand(2, 6)),

            // تكرار مسافة سطر في أماكن عشوائية
            function ($msg) {
                $lines = explode("\n", $msg);
                if (count($lines) > 2) {
                    $pos = rand(1, count($lines) - 2);
                    array_splice($lines, $pos, 0, ['']);
                }
                return implode("\n", $lines);
            },
        ];

        // نختار من 2 إلى 4 تعديلات شكلية
        $count = rand(2, 4);
        $selected = array_rand($shapeVariations, $count);
        if (!is_array($selected)) {
            $selected = [$selected];
        }

        foreach ($selected as $idx) {
            $body = $shapeVariations[$idx]($body);
        }

        return trim($body) ?: $message;
    }
}