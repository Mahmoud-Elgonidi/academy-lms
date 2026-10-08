<?php

/*
 * Laravel's own checks in Arabic (Egyptian, as everywhere in the app): what
 * Filament's fields and the Actions' validators say when a field is empty or
 * out of range. A field's name comes in as :attribute; a rule missing here
 * falls back to English.
 */
return [
    'accepted' => 'لازم توافق على :attribute.',
    'after' => ':attribute لازم يكون بعد :date.',
    'after_or_equal' => ':attribute لازم يكون :date أو بعده.',
    'alpha_dash' => ':attribute فيه حروف وأرقام و - و _ بس.',
    'array' => ':attribute لازم يكون قايمة.',
    'before' => ':attribute لازم يكون قبل :date.',
    'before_or_equal' => ':attribute لازم يكون :date أو قبله.',
    'between' => [
        'array' => ':attribute من :min لـ :max عناصر.',
        'file' => 'حجم :attribute من :min لـ :max كيلوبايت.',
        'numeric' => ':attribute رقم من :min لـ :max.',
        'string' => ':attribute من :min لـ :max حرف.',
    ],
    'boolean' => ':attribute أيوه أو لأ.',
    'confirmed' => 'تأكيد :attribute مش زيه.',
    'date' => ':attribute مش تاريخ صحيح.',
    'date_format' => ':attribute مش مكتوب بالشكل :format.',
    'decimal' => ':attribute لازم يكون فيه :decimal رقم بعد العلامة.',
    'different' => ':attribute و:other لازم يكونوا مختلفين.',
    'digits' => ':attribute لازم يكون :digits أرقام.',
    'digits_between' => ':attribute من :min لـ :max أرقام.',
    'distinct' => ':attribute متكرر.',
    'email' => ':attribute مش إيميل صحيح.',
    'exists' => ':attribute اللي اخترته مش موجود.',
    'file' => ':attribute لازم يكون ملف.',
    'filled' => 'خانة :attribute لازم تتملي.',
    'gt' => [
        'numeric' => ':attribute لازم يكون أكبر من :value.',
        'string' => ':attribute لازم يكون أكتر من :value حرف.',
    ],
    'gte' => [
        'numeric' => ':attribute لازم يكون :value أو أكتر.',
        'string' => ':attribute لازم يكون :value حرف أو أكتر.',
    ],
    'image' => ':attribute لازم يكون صورة.',
    'in' => ':attribute اللي اخترته مش من الاختيارات.',
    'integer' => ':attribute لازم يكون رقم صحيح.',
    'json' => ':attribute مش JSON صحيح.',
    'lt' => [
        'numeric' => ':attribute لازم يكون أقل من :value.',
        'string' => ':attribute لازم يكون أقل من :value حرف.',
    ],
    'lte' => [
        'numeric' => ':attribute لازم يكون :value أو أقل.',
        'string' => ':attribute لازم يكون :value حرف أو أقل.',
    ],
    'max' => [
        'array' => ':attribute :max عناصر بالكتير.',
        'file' => 'حجم :attribute :max كيلوبايت بالكتير.',
        'numeric' => ':attribute :max بالكتير.',
        'string' => ':attribute :max حرف بالكتير.',
    ],
    'mimes' => ':attribute لازم يكون ملف من النوع: :values.',
    'mimetypes' => ':attribute لازم يكون ملف من النوع: :values.',
    'min' => [
        'array' => ':attribute :min عناصر على الأقل.',
        'file' => 'حجم :attribute :min كيلوبايت على الأقل.',
        'numeric' => ':attribute :min على الأقل.',
        'string' => ':attribute :min حرف على الأقل.',
    ],
    'not_in' => ':attribute اللي اخترته مش مسموح.',
    'numeric' => ':attribute لازم يكون رقم.',
    'present' => 'خانة :attribute لازم تكون موجودة.',
    'regex' => ':attribute مش مكتوب صح.',
    'required' => 'خانة :attribute مطلوبة.',
    'required_if' => 'خانة :attribute مطلوبة لما :other يكون :value.',
    'required_unless' => 'خانة :attribute مطلوبة إلا لو :other من :values.',
    'required_with' => 'خانة :attribute مطلوبة مع :values.',
    'required_without' => 'خانة :attribute مطلوبة لو :values مش موجود.',
    'same' => ':attribute و:other لازم يكونوا زي بعض.',
    'size' => [
        'array' => ':attribute لازم يكون فيه :size عناصر.',
        'file' => 'حجم :attribute لازم يكون :size كيلوبايت.',
        'numeric' => ':attribute لازم يكون :size.',
        'string' => ':attribute لازم يكون :size حرف.',
    ],
    'string' => ':attribute لازم يكون كلام.',
    'timezone' => ':attribute مش منطقة زمنية صحيحة.',
    'unique' => ':attribute موجود قبل كده.',
    'uploaded' => ':attribute ماترفعش.',
    'url' => ':attribute مش لينك صحيح.',
    'uuid' => ':attribute مش معرّف صحيح.',

    'attributes' => [],
];
