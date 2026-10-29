<?php

/*
 * Laravel's own checks in Arabic (Modern Standard Arabic, as everywhere in the
 * app): what Filament's fields and the Actions' validators say when a field is
 * empty or out of range. A field's name comes in as :attribute; a rule missing
 * here falls back to English.
 */
return [
    'accepted' => 'يجب أن توافق على :attribute.',
    'after' => 'يجب أن يكون :attribute بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute في :date أو بعده.',
    'alpha_dash' => 'يحتوي :attribute على حروف وأرقام و - و _ فقط.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'before' => 'يجب أن يكون :attribute قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute في :date أو قبله.',
    'between' => [
        'array' => ':attribute من :min إلى :max عناصر.',
        'file' => 'حجم :attribute من :min إلى :max كيلوبايت.',
        'numeric' => ':attribute رقم من :min إلى :max.',
        'string' => ':attribute من :min إلى :max حرف.',
    ],
    'boolean' => ':attribute نعم أو لا.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'date' => ':attribute ليس تاريخًا صحيحًا.',
    'date_format' => ':attribute غير مكتوب بالشكل :format.',
    'decimal' => 'يجب أن يحتوي :attribute على :decimal أرقام بعد الفاصلة.',
    'different' => 'يجب أن يختلف :attribute عن :other.',
    'digits' => 'يجب أن يكون :attribute :digits أرقام.',
    'digits_between' => ':attribute من :min إلى :max أرقام.',
    'distinct' => ':attribute مكرر.',
    'email' => ':attribute ليس بريدًا إلكترونيًا صحيحًا.',
    'exists' => ':attribute الذي اخترته غير موجود.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'filled' => 'يجب ملء خانة :attribute.',
    'gt' => [
        'numeric' => 'يجب أن يكون :attribute أكبر من :value.',
        'string' => 'يجب أن يكون :attribute أكثر من :value حرف.',
    ],
    'gte' => [
        'numeric' => 'يجب أن يكون :attribute :value أو أكثر.',
        'string' => 'يجب أن يكون :attribute :value حرف أو أكثر.',
    ],
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => ':attribute الذي اخترته ليس من الخيارات.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'json' => ':attribute ليس JSON صحيحًا.',
    'lt' => [
        'numeric' => 'يجب أن يكون :attribute أقل من :value.',
        'string' => 'يجب أن يكون :attribute أقل من :value حرف.',
    ],
    'lte' => [
        'numeric' => 'يجب أن يكون :attribute :value أو أقل.',
        'string' => 'يجب أن يكون :attribute :value حرف أو أقل.',
    ],
    'max' => [
        'array' => ':attribute :max عناصر على الأكثر.',
        'file' => 'حجم :attribute :max كيلوبايت على الأكثر.',
        'numeric' => ':attribute :max على الأكثر.',
        'string' => ':attribute :max حرف على الأكثر.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'min' => [
        'array' => ':attribute :min عناصر على الأقل.',
        'file' => 'حجم :attribute :min كيلوبايت على الأقل.',
        'numeric' => ':attribute :min على الأقل.',
        'string' => ':attribute :min حرف على الأقل.',
    ],
    'not_in' => ':attribute الذي اخترته غير مسموح.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'present' => 'يجب أن تكون خانة :attribute موجودة.',
    'regex' => ':attribute غير مكتوب بشكل صحيح.',
    'required' => 'خانة :attribute مطلوبة.',
    'required_if' => 'خانة :attribute مطلوبة عندما يكون :other :value.',
    'required_unless' => 'خانة :attribute مطلوبة إلا إذا كان :other من :values.',
    'required_with' => 'خانة :attribute مطلوبة مع :values.',
    'required_without' => 'خانة :attribute مطلوبة إن لم يوجد :values.',
    'same' => 'يجب أن يتطابق :attribute و:other.',
    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عناصر.',
        'file' => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن يكون :attribute :size.',
        'string' => 'يجب أن يكون :attribute :size حرف.',
    ],
    'string' => 'يجب أن يكون :attribute نصًا.',
    'timezone' => ':attribute ليس منطقة زمنية صحيحة.',
    'unique' => ':attribute موجود من قبل.',
    'uploaded' => 'لم يُرفع :attribute.',
    'url' => ':attribute ليس رابطًا صحيحًا.',
    'uuid' => ':attribute ليس معرّفًا صحيحًا.',

    'attributes' => [],
];
