<?php

namespace Modules\Platform\Settings;

use Modules\Platform\Settings\Rules\OllamaAddress;

/**
 * Every setting the console's Platform settings screen offers: the outside
 * services (AI, email, WhatsApp, texts, push, the classroom, Stripe Connect,
 * Google and Apple sign-in) and the platform's own words and links. What the
 * stack itself runs on (APP_KEY, the database, Redis, Reverb, domains, CORS,
 * backups, the scanner) stays in the server's .env alone.
 *
 * A value set here wins over the .env line it stands in for; cleared, the
 * .env value is back. Wording: platform::settings.keys.{key}.
 */
final class SettingCatalog
{
    /** The screen's sections, in order. */
    public const GROUPS = ['ai', 'mail', 'whatsapp', 'sms', 'push', 'classroom', 'stripe', 'social', 'platform'];

    private const ID = ['regex:/^[A-Za-z0-9._:\-]+$/', 'max:200'];

    private const KEY = ['regex:/^\S+$/', 'max:2000'];

    private const MODEL = ['regex:/^[A-Za-z0-9][A-Za-z0-9._:\-]*$/', 'max:120'];

    /** Ollama's names may name a repository and a tag (hf.co/user/model:Q4_K_M). */
    private const LOCAL_MODEL = ['regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\-]*$/', 'max:200'];

    /** @var ?array<string, SettingDefinition> */
    private static ?array $all = null;

    /** @return array<string, SettingDefinition> key => definition */
    public static function all(): array
    {
        return self::$all ??= collect([
            // The AI helpers and the games designer: who writes the words, then that provider's key and models.
            new SettingDefinition('ai_driver', 'ai', 'select', ['platform.ai.driver'], 'AI_DRIVER', ['claude', 'openai', 'gemini', 'ollama', 'off']),
            new SettingDefinition('anthropic_key', 'ai', 'secret', ['platform.ai.key', 'platform.games.designer.key'], 'ANTHROPIC_API_KEY', rules: self::KEY),
            new SettingDefinition('ai_model', 'ai', 'model', ['platform.ai.models.best'], 'AI_MODEL', rules: self::MODEL, driver: 'claude', models: 'claude'),
            new SettingDefinition('ai_fast_model', 'ai', 'model', ['platform.ai.models.fast'], 'AI_FAST_MODEL', rules: self::MODEL, driver: 'claude', models: 'claude'),
            new SettingDefinition('openai_key', 'ai', 'secret', ['platform.ai.openai.key'], 'OPENAI_API_KEY', rules: self::KEY, driver: 'openai'),
            new SettingDefinition('openai_model', 'ai', 'model', ['platform.ai.openai.models.best'], 'OPENAI_MODEL', rules: self::MODEL, driver: 'openai', models: 'openai'),
            new SettingDefinition('openai_fast_model', 'ai', 'model', ['platform.ai.openai.models.fast'], 'OPENAI_FAST_MODEL', rules: self::MODEL, driver: 'openai', models: 'openai'),
            new SettingDefinition('gemini_key', 'ai', 'secret', ['platform.ai.gemini.key'], 'GEMINI_API_KEY', rules: self::KEY, driver: 'gemini'),
            new SettingDefinition('gemini_model', 'ai', 'model', ['platform.ai.gemini.models.best'], 'GEMINI_MODEL', rules: self::MODEL, driver: 'gemini', models: 'gemini'),
            new SettingDefinition('gemini_fast_model', 'ai', 'model', ['platform.ai.gemini.models.fast'], 'GEMINI_FAST_MODEL', rules: self::MODEL, driver: 'gemini', models: 'gemini'),
            new SettingDefinition('ollama_url', 'ai', 'url', ['platform.ai.ollama.url'], 'OLLAMA_URL', rules: ['url:https,http', 'max:300', new OllamaAddress], driver: 'ollama'),
            new SettingDefinition('ollama_key', 'ai', 'secret', ['platform.ai.ollama.key'], 'OLLAMA_API_KEY', rules: self::KEY, driver: 'ollama'),
            new SettingDefinition('ollama_model', 'ai', 'model', ['platform.ai.ollama.models.best'], 'OLLAMA_MODEL', rules: self::LOCAL_MODEL, driver: 'ollama', models: 'ollama'),
            new SettingDefinition('ollama_fast_model', 'ai', 'model', ['platform.ai.ollama.models.fast'], 'OLLAMA_FAST_MODEL', rules: self::LOCAL_MODEL, driver: 'ollama', models: 'ollama'),
            new SettingDefinition('game_designer_model', 'ai', 'model', ['platform.games.designer.model'], 'GAME_DESIGNER_MODEL', rules: self::MODEL, models: 'claude'),
            new SettingDefinition('ai_daily_cap', 'ai', 'int', ['platform.ai.daily_cap'], 'AI_DAILY_CAP', rules: ['integer', 'min:0', 'max:100000'], cast: 'int'),
            new SettingDefinition('elevenlabs_key', 'ai', 'secret', ['platform.ai.speech.key'], 'ELEVENLABS_API_KEY', rules: self::KEY),
            new SettingDefinition('elevenlabs_base', 'ai', 'select', ['platform.ai.speech.base_url'], 'ELEVENLABS_API_BASE', ['world', 'eu'],
                map: ['world' => 'https://api.elevenlabs.io', 'eu' => 'https://api.eu.residency.elevenlabs.io']),
            new SettingDefinition('elevenlabs_zero_retention', 'ai', 'toggle', ['platform.ai.speech.zero_retention'], 'ELEVENLABS_ZERO_RETENTION', cast: 'bool'),

            // Email (SMTP).
            new SettingDefinition('mail_mailer', 'mail', 'select', ['mail.default'], 'MAIL_MAILER', ['smtp', 'log']),
            new SettingDefinition('mail_host', 'mail', 'text', ['mail.mailers.smtp.host'], 'MAIL_HOST', rules: ['regex:/^[A-Za-z0-9.\-]+$/', 'max:190']),
            new SettingDefinition('mail_port', 'mail', 'int', ['mail.mailers.smtp.port'], 'MAIL_PORT', rules: ['integer', 'min:1', 'max:65535'], cast: 'int'),
            new SettingDefinition('mail_scheme', 'mail', 'select', ['mail.mailers.smtp.scheme'], 'MAIL_SCHEME', ['smtp', 'smtps']),
            new SettingDefinition('mail_username', 'mail', 'text', ['mail.mailers.smtp.username'], 'MAIL_USERNAME', rules: ['max:190']),
            new SettingDefinition('mail_password', 'mail', 'secret', ['mail.mailers.smtp.password'], 'MAIL_PASSWORD', rules: ['max:500']),
            new SettingDefinition('mail_from_address', 'mail', 'email', ['mail.from.address'], 'MAIL_FROM_ADDRESS', rules: ['email', 'max:190']),
            new SettingDefinition('mail_from_name', 'mail', 'text', ['mail.from.name'], 'MAIL_FROM_NAME', rules: ['max:120']),
            new SettingDefinition('mail_timeout', 'mail', 'int', ['mail.mailers.smtp.timeout'], 'MAIL_TIMEOUT', rules: ['integer', 'min:1', 'max:120'], cast: 'int'),

            // WhatsApp (Meta's cloud API).
            new SettingDefinition('whatsapp_driver', 'whatsapp', 'select', ['communication.whatsapp.driver'], 'WHATSAPP_DRIVER', ['meta', 'log']),
            new SettingDefinition('whatsapp_token', 'whatsapp', 'secret', ['communication.whatsapp.meta.token'], 'WHATSAPP_META_TOKEN', rules: self::KEY),
            new SettingDefinition('whatsapp_phone_number_id', 'whatsapp', 'text', ['communication.whatsapp.meta.phone_number_id'], 'WHATSAPP_META_PHONE_NUMBER_ID', rules: ['regex:/^\d{5,30}$/']),
            new SettingDefinition('whatsapp_template', 'whatsapp', 'text', ['communication.whatsapp.meta.template'], 'WHATSAPP_META_TEMPLATE', rules: ['regex:/^[a-z0-9_]{1,512}$/']),
            new SettingDefinition('whatsapp_otp_template', 'whatsapp', 'text', ['communication.whatsapp.meta.otp_template'], 'WHATSAPP_META_OTP_TEMPLATE', rules: ['regex:/^[a-z0-9_]{1,512}$/']),

            // Texts (Twilio), for sign-in codes only.
            new SettingDefinition('sms_driver', 'sms', 'select', ['communication.sms.driver'], 'SMS_DRIVER', ['twilio', 'log', 'none']),
            new SettingDefinition('twilio_sid', 'sms', 'text', ['communication.sms.twilio.sid'], 'TWILIO_ACCOUNT_SID', rules: ['regex:/^AC[0-9a-fA-F]{32}$/']),
            new SettingDefinition('twilio_token', 'sms', 'secret', ['communication.sms.twilio.token'], 'TWILIO_AUTH_TOKEN', rules: self::KEY),
            new SettingDefinition('twilio_from', 'sms', 'text', ['communication.sms.twilio.from'], 'TWILIO_FROM', rules: ['regex:/^(\+\d{6,15}|[A-Za-z0-9 ]{1,11})$/']),
            new SettingDefinition('twilio_messaging_service_sid', 'sms', 'text', ['communication.sms.twilio.messaging_service_sid'], 'TWILIO_MESSAGING_SERVICE_SID', rules: ['regex:/^MG[0-9a-fA-F]{32}$/']),

            // Push (Firebase Cloud Messaging): the service account's JSON, kept here instead of a file on the server.
            new SettingDefinition('push_driver', 'push', 'select', ['communication.push.driver'], 'PUSH_DRIVER', ['fcm', 'log']),
            new SettingDefinition('fcm_project_id', 'push', 'text', ['communication.push.fcm.project_id'], 'FCM_PROJECT_ID', rules: ['regex:/^[a-z0-9\-]{4,40}$/']),
            new SettingDefinition('fcm_credentials', 'push', 'json_file', ['communication.push.fcm.credentials_json'], 'FCM_CREDENTIALS'),

            // The classroom (LiveKit).
            new SettingDefinition('classroom_provider', 'classroom', 'select', ['classroom.provider'], 'CLASSROOM_PROVIDER', ['livekit', 'external']),
            new SettingDefinition('livekit_url', 'classroom', 'url', ['classroom.livekit.url'], 'LIVEKIT_URL', rules: ['url:wss,ws,https', 'max:300']),
            new SettingDefinition('livekit_api_url', 'classroom', 'url', ['classroom.livekit.api_url'], 'LIVEKIT_API_URL', rules: ['url:https,http', 'max:300']),
            new SettingDefinition('livekit_key', 'classroom', 'text', ['classroom.livekit.key'], 'LIVEKIT_API_KEY', rules: self::ID),
            new SettingDefinition('livekit_secret', 'classroom', 'secret', ['classroom.livekit.secret'], 'LIVEKIT_API_SECRET', rules: [...self::KEY, 'min:32']),
            new SettingDefinition('livekit_egress_url', 'classroom', 'url', ['classroom.livekit.egress_url'], 'LIVEKIT_EGRESS_URL', rules: ['url:https,http', 'max:300']),

            // Stripe Connect: academies connect their own Stripe account to the platform.
            new SettingDefinition('stripe_platform_secret', 'stripe', 'secret', ['billing.payments.stripe.platform_secret'], 'STRIPE_PLATFORM_SECRET', rules: ['regex:/^(sk|rk)_(live|test)_\S+$/', 'max:300']),
            new SettingDefinition('stripe_connect_client_id', 'stripe', 'text', ['billing.payments.stripe.connect_client_id'], 'STRIPE_CONNECT_CLIENT_ID', rules: ['regex:/^ca_[A-Za-z0-9]+$/', 'max:100']),
            new SettingDefinition('stripe_connect_webhook_secret', 'stripe', 'secret', ['billing.payments.stripe.connect_webhook_secret'], 'STRIPE_CONNECT_WEBHOOK_SECRET', rules: ['regex:/^whsec_\S+$/', 'max:300']),
            new SettingDefinition('stripe_connect_redirect', 'stripe', 'url', ['billing.payments.stripe.connect_redirect'], 'STRIPE_CONNECT_REDIRECT', rules: ['url:https', 'max:300']),
            new SettingDefinition('stripe_application_fee_bp', 'stripe', 'int', ['billing.payments.stripe.application_fee_bp'], 'STRIPE_APPLICATION_FEE_BP', rules: ['integer', 'min:0', 'max:5000'], cast: 'int'),

            // Sign-in with Google and Apple: the platform's clients.
            new SettingDefinition('google_client_id', 'social', 'text', ['identity.social.google.client_id'], 'GOOGLE_CLIENT_ID', rules: self::ID),
            new SettingDefinition('google_client_secret', 'social', 'secret', ['identity.social.google.client_secret'], 'GOOGLE_CLIENT_SECRET', rules: self::KEY),
            new SettingDefinition('google_app_client_ids', 'social', 'list', ['identity.social.google.app_audiences'], 'GOOGLE_APP_CLIENT_IDS', rules: self::ID),
            new SettingDefinition('apple_services_id', 'social', 'text', ['identity.social.apple.client_id'], 'APPLE_SERVICES_ID', rules: self::ID),
            new SettingDefinition('apple_app_ids', 'social', 'list', ['identity.social.apple.app_audiences'], 'APPLE_APP_IDS', rules: self::ID),

            // The platform's own name and addresses.
            new SettingDefinition('legal_name', 'platform', 'text', ['privacy.platform_name'], 'PLATFORM_LEGAL_NAME', rules: ['max:150']),
            new SettingDefinition('privacy_contact_email', 'platform', 'email', ['privacy.contact_email'], 'PRIVACY_CONTACT_EMAIL', rules: ['email', 'max:190']),
            new SettingDefinition('health_alert_to', 'platform', 'list', ['platform.health.alert_to'], 'HEALTH_ALERT_TO', rules: ['email', 'max:190'], cast: 'array'),
            new SettingDefinition('app_min_version', 'platform', 'text', ['platform.app.min_version'], 'APP_MIN_VERSION', rules: ['regex:/^\d+\.\d+\.\d+$/']),
            new SettingDefinition('app_android_url', 'platform', 'url', ['platform.app.android_url'], 'APP_ANDROID_URL', rules: ['url:https', 'max:300']),
            new SettingDefinition('app_ios_url', 'platform', 'url', ['platform.app.ios_url'], 'APP_IOS_URL', rules: ['url:https', 'max:300']),
        ])->keyBy('key')->all();
    }

    public static function get(string $key): ?SettingDefinition
    {
        return self::all()[$key] ?? null;
    }

    /** @return list<SettingDefinition> */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(self::all(), fn (SettingDefinition $d) => $d->group === $group));
    }
}
