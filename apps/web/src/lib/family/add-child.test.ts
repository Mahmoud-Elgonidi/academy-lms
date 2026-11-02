import { describe, expect, it } from 'vitest';
import { EMPTY_CHILD, birthYears, childBody, fillChild, filledWording, missing, refusalsOf } from './add-child';

describe('adding a child', () => {
  it('sends the birth the way it was given, and the name tidied', () => {
    expect(childBody({ ...EMPTY_CHILD, name: '  يارا   سالم ', birthYear: '2018', gender: 'female', agree: true }, 1)).toEqual({
      name: 'يارا سالم', birth_date: null, birth_year: 2018, gender: 'female', agree: true, version: 1,
    });
    expect(childBody({ ...EMPTY_CHILD, name: 'Omar', birthBy: 'date', birthDate: '2017-04-09', birthYear: '2015', agree: true }, 2)).toMatchObject({
      birth_date: '2017-04-09', birth_year: null, gender: null, version: 2,
    });
  });

  it('asks for what is missing before sending', () => {
    expect(missing(EMPTY_CHILD)).toEqual(['name', 'birth', 'agree']);
    expect(missing({ ...EMPTY_CHILD, name: 'Ya', birthYear: '2019', agree: true })).toEqual([]);
    expect(missing({ ...EMPTY_CHILD, name: 'Ya', birthBy: 'date', birthYear: '2019', agree: true })).toEqual(['birth']);
  });

  it('offers this year back 25 years', () => {
    const years = birthYears(2026);
    expect(years[0]).toBe(2026);
    expect(years.at(-1)).toBe(2002);
    expect(years).toHaveLength(25);
  });

  it('puts the name being typed where {child} stands', () => {
    expect(fillChild('موافقتك على حساب {child}', ' يارا ', 'طفلك')).toBe('موافقتك على حساب يارا');
    expect(fillChild('أنا ولي أمر {child} و{child}', '', 'طفلك')).toBe('أنا ولي أمر طفلك وطفلك');
    const text = filledWording({ version: 3, title: '{child}', intro: 'i {child}', sections: [{ title: 't', body: 'b {child}' }], agree: 'a {child}' }, 'Yara', 'your child');
    expect(text).toEqual({ version: 3, title: 'Yara', intro: 'i Yara', sections: [{ title: 't', body: 'b Yara' }], agree: 'a Yara' });
  });

  it('puts each refusal by its field, the rest above the form', () => {
    expect(refusalsOf({ name: ['في حسابكم طفل بالاسم نفسه'] }, 'The given data was invalid.')).toEqual({ fields: { name: 'في حسابكم طفل بالاسم نفسه' }, form: null, wordingChanged: false });
    expect(refusalsOf({ birth_year: ['سنة الميلاد غير منطقية'] })).toMatchObject({ fields: { birth: 'سنة الميلاد غير منطقية' } });
    expect(refusalsOf({ version: ['تغيّر النص'] })).toEqual({ fields: {}, form: 'تغيّر النص', wordingChanged: true });
    // A 403 (not a guardian): the API's words above the form.
    expect(refusalsOf({}, 'إضافة طفل متاحة لحساب وليّ الأمر')).toEqual({ fields: {}, form: 'إضافة طفل متاحة لحساب وليّ الأمر', wordingChanged: false });
    expect(refusalsOf({}, '')).toEqual({ fields: {}, form: null, wordingChanged: false });
  });
});
