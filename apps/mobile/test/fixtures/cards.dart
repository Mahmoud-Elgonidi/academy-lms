import '../helpers.dart';

const fxMandateText = 'أوافق على أن تحفظ بوابة الدفع بطاقتي، وأن تُخصم منها فاتورة كل باقة تالية بعد إبلاغي بثلاثة أيام.';

/// A saved card as the API shows it: brand, last four and expiry only.
Map<String, Object?> fxCard({String id = 'pm1', bool soon = false}) => {
  'id': id,
  'brand': 'visa',
  'brand_label': 'Visa',
  'last4': '4242',
  'exp_month': 12,
  'exp_year': 2034,
  'label': 'Visa ****4242',
  'account': 'بطاقة (Moyasar)',
  'expires_soon': soon,
  'saved_at': time('2099-10-01T09:00:00Z', '2099-10-01T12:00:00+03:00', label: 'الخميس 1 أكتوبر · 12:00 م'),
};

/// `GET family/cards`: [cards] kept, automatic renewal [on], and a charge coming when [coming].
Map<String, Object?> fxCards({
  bool card = true,
  bool on = true,
  bool coming = true,
  bool soon = false,
  List<Map<String, Object?>>? accounts,
}) => {
  'data': {
    'cards': card ? [fxCard(soon: soon)] : <Object>[],
    'accounts':
        accounts ??
        [
          {'id': 'ba1', 'currency': 'SAR', 'auto_renew': on && card, 'card_id': card ? 'pm1' : null},
        ],
    'auto_renew': on && card,
    'coming': coming && on && card
        ? [
            {
              'invoice_id': 'i9',
              'number': 'INV-2099-0009',
              'amount': '400 ر.س.',
              'amount_minor': 40000,
              'currency': 'SAR',
              'status': 'scheduled',
              'at': time('2099-10-15T15:00:00Z', '2099-10-15T18:00:00+03:00', label: 'الخميس 15 أكتوبر · 6:00 م'),
            },
          ]
        : <Object>[],
    'mandate': {'version': '2027-07-20', 'text': fxMandateText},
  },
};

/// A course balance's `automatic_renewal`, on with the waiting invoice's charge, or off with the card kept.
Map<String, Object?> fxCourseAuto({bool on = true, bool soon = false}) => {
  'on': on,
  'card': {
    'id': 'pm1',
    'brand': 'visa',
    'last4': '4242',
    'exp_month': 12,
    'exp_year': 2034,
    'label': 'Visa ****4242',
    'expires_soon': soon,
  },
  'next_charge': on
      ? {
          'at': time('2099-10-15T15:00:00Z', '2099-10-15T18:00:00+03:00', label: 'الخميس 15 أكتوبر · 6:00 م'),
          'amount': '400 ر.س.',
          'amount_minor': 40000,
          'status': 'scheduled',
        }
      : null,
  'text': on
      ? 'التجديد التلقائي يعمل: تُخصم الباقة التالية من Visa ****4242 بعد إبلاغكم بثلاثة أيام.'
      : 'التجديد التلقائي متوقف. بطاقتكم Visa ****4242 محفوظة.',
};
