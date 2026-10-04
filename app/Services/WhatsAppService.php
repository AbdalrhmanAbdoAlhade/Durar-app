<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected static string $endpoint = 'https://bakewats.zh-innovation.tech/api/sessions/durar/send';

    /**
     * رسالة ترحيب بعد إنشاء الحساب
     * (ماترميش exception أبداً، عشان الـ register ميتأثرش)
     */
    public static function accountCreated(User $user): void
    {
        if (empty($user->phone)) {
            return;
        }

        try {
            $brand = config('app.name');

            $message = "أهلاً {$user->name}\n"
                . "تم إنشاء حسابك بنجاح في {$brand}.\n"
                . "نورتنا 🙏";

            $result = static::sendMessage($user->phone, $message, false);

            if (! ($result['success'] ?? false)) {
                Log::warning('WhatsApp accountCreated not sent', [
                    'user_id' => $user->id,
                    'result'  => $result,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp accountCreated failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * إرسال رسالة واتساب (مع تنويع اختياري + تأخير عشوائي بسيط)
     */
    public static function sendMessage(string $to, string $message, bool $vary = true): array
    {
        $cleanPhone = self::cleanPhone($to);

        if (! $cleanPhone) {
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
                    'success'        => true,
                    'message'        => 'تم الإرسال بنجاح',
                    'response'       => $body,
                    'varied_message' => $finalMessage,
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
        } catch (\Throwable $e) {
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
     * تنظيف رقم الهاتف وتحويله لصيغة دولية
     */
    protected static function cleanPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // إزالة أي حاجة غير الأرقام
        $clean = preg_replace('/[^0-9]/', '', $phone);

        // إزالة 00 من البداية (رمز الاتصال الدولي)
        if (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        // رقم محلي بيبدأ بـ 0 → صيغة مصر الدولية
        if (str_starts_with($clean, '0')) {
            $clean = '20' . substr($clean, 1);
        }

        // إصلاح: 200xxxxxxxxx (صفر زيادة بعد كود الدولة)
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
     * تغيير شكل الرسالة (anti-ban)
     */
    protected static function varyMessageStrong(string $message): string
    {
        $brand = config('app.name');

        // جمل ترحيبية عشوائية (تُضاف في البداية أحياناً)
        $greetings = [
            'مرحباً بك 👋',
            'أهلاً وسهلاً ✨',
            'يسعدنا تواصلك معنا 💎',
            'تحية طيبة 🌟',
            "مرحباً بيك في عائلة {$brand} ❤️",
            'نورتنا 🙏',
            '', // احتمال ما نضيفش حاجة
        ];

        // جمل ختامية عشوائية
        $closings = [
            'مع خالص التحية ❤️',
            'نتمنى لك يوماً سعيداً ✨',
            'في انتظارك دائماً 💎',
            'شكراً لثقتك بنا 🙏',
            "فريق {$brand} يتمنى لك التوفيق 🌟",
            'يسعدنا خدمتك دائماً 👋',
            '', // احتمال ما نضيفش
        ];

        // مرادفات بسيطة (كل كلمة بتتبدل بكلمة بنفس المعنى والنحو)
        $replacements = [
            'مرحباً' => ['مرحباً', 'أهلاً', 'أهلاً بك', 'مرحبا بيك'],
            'نشكر'   => ['نشكر', 'نقدر'],
            'يسعدنا' => ['يسعدنا', 'يسرّنا', 'نسعد'],
            'نرجو'   => ['نرجو', 'نأمل'],
            'سيتم'   => ['سيتم', 'هيتم', 'سوف يتم'],
            'خلال'   => ['خلال', 'في غضون'],
        ];

        foreach ($replacements as $word => $alternatives) {
            if (str_contains($message, $word) && rand(0, 1)) {
                $message = str_replace($word, $alternatives[array_rand($alternatives)], $message);
            }
        }

        // تقسيم الرسالة إلى جمل
        $sentences = preg_split('/(?<=[.!؟\n])\s+/u', trim($message), -1, PREG_SPLIT_NO_EMPTY);

        // احتمال تبديل جملتين (بحذر عشان المعنى ميبوظش)
        if (count($sentences) > 3 && rand(0, 1)) {
            $mid  = (int) (count($sentences) / 2);
            $last = count($sentences) - 1;

            [$sentences[$mid], $sentences[$last]] = [$sentences[$last], $sentences[$mid]];
        }

        $body = implode("\n", $sentences);

        // ترحيب في البداية (احتمال 60%)
        $greeting = $greetings[array_rand($greetings)];
        if ($greeting && rand(1, 10) <= 6) {
            $body = $greeting . "\n\n" . $body;
        }

        // ختام في النهاية (احتمال 50%)
        $closing = $closings[array_rand($closings)];
        if ($closing && rand(1, 10) <= 5) {
            $body .= "\n\n" . $closing;
        }

        // تعديلات شكلية إضافية
        $shapeVariations = [
            // مسافات في النهاية
            fn ($msg) => $msg . str_repeat(' ', rand(1, 5)),

            // سطور فارغة في النهاية
            fn ($msg) => $msg . str_repeat("\n", rand(1, 3)),

            // رمز تعبيري في البداية أو النهاية
            function ($msg) {
                $emojis = ['✨', '💎', '🌟', '✅', '📌', '🔔', '💫', '❤️', '🙏', '👋', '🎉'];
                $emoji  = $emojis[array_rand($emojis)];

                return rand(0, 1) ? $emoji . ' ' . $msg : $msg . ' ' . $emoji;
            },

            // فواصل زخرفية
            function ($msg) {
                $seps = ["\n---\n", "\n• • •\n", "\n————\n", "\n✦ ✦ ✦\n"];

                return $msg . $seps[array_rand($seps)];
            },

            // Zero-width spaces (غير مرئية)
            fn ($msg) => $msg . str_repeat("\u{200B}", rand(2, 6)),

            // سطر فارغ في مكان عشوائي
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
        $selected = array_rand($shapeVariations, rand(2, 4));
        $selected = (array) $selected;

        foreach ($selected as $idx) {
            $body = $shapeVariations[$idx]($body);
        }

        return trim($body) ?: $message;
    }
}