<?php

return [
    'names' => [
        'anthropic' => 'Claude (Anthropic)',
        'openai' => 'GPT (OpenAI)',
        'gemini' => 'Gemini (Google)',
        'ollama' => 'Ollama',
        'elevenlabs' => 'ElevenLabs',
        'whatsapp' => 'WhatsApp (Meta)',
        'twilio' => 'Twilio',
        'fcm' => 'Firebase (push)',
        'livekit' => 'LiveKit',
        'stripe' => 'Stripe',
    ],
    // What went wrong, in full.
    'problems' => [
        'key' => ':service refused the key: it is wrong, revoked or expired.',
        'permission' => ':service took the key, but it may not do this (its permissions, the region, or the service is not enabled on the account).',
        'quota' => ':service says the credit, quota or subscription ran out.',
        'suspended' => ':service has suspended or closed the account.',
        'model' => ':service does not offer the model or template asked for to this key.',
        'config' => 'Something is missing or wrong in the :service account or in its settings here (a sender, a project, a template).',
        'rate' => ':service is limiting how many requests we send for now.',
        'down' => ':service is having trouble on its side.',
        'unreachable' => ':service did not answer: check the address and that the server can reach it.',
        'unknown' => ':service answered with an error we do not know.',
    ],
    // The same in a few words.
    'short' => [
        'key' => 'key refused',
        'permission' => 'not allowed',
        'quota' => 'credit or subscription ran out',
        'suspended' => 'account suspended',
        'model' => 'model or template not there',
        'config' => 'something set up wrong',
        'rate' => 'too many requests',
        'down' => 'trouble on its side',
        'unreachable' => 'did not answer',
        'unknown' => 'an unknown error',
    ],
    // What to do.
    'todo' => [
        'key' => 'Make a new key at :service, put it in Platform settings, then try it.',
        'permission' => 'Give the key what it needs at :service (or enable the service on the account), then try it.',
        'quota' => 'Top up the credit or renew the plan at :service, then try it.',
        'suspended' => 'Sign in at :service and see why the account is stopped.',
        'model' => 'Choose another model or template in Platform settings, or check it is enabled for the account at :service.',
        'config' => 'Check Platform settings against the account at :service.',
        'rate' => 'It usually passes in a while; if it lasts, raise the limits at :service.',
        'down' => 'It usually passes on its own; see :service\'s status page.',
        'unreachable' => 'Check the address in Platform settings and the server\'s network.',
        'unknown' => 'Try it from Platform settings; if it lasts, look at the account at :service.',
    ],
    'used' => [
        'ok' => 'answered :when',
        'never' => 'not used or tried yet',
        'failing' => ':problem since :since (:count in a row, :code)',
        'near' => ':pct% of the plan used',
        'stale' => 'not checked since :when',
    ],
];
