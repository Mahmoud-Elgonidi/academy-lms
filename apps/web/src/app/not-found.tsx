// A path outside any locale: the proxy sends "/" to a locale, so this is rare.
export default function NotFound() {
  return (
    <html lang="ar" dir="rtl">
      <body style={{ fontFamily: 'system-ui, sans-serif', display: 'grid', placeItems: 'center', minHeight: '100dvh', margin: 0 }}>
        <p>
          <a href="/">هذه الصفحة غير موجودة · This page does not exist</a>
        </p>
      </body>
    </html>
  );
}
