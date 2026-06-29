# Değişiklik Günlüğü (Changelog)

**iyzico Kolai for WooCommerce** eklentisindeki tüm önemli değişiklikler bu dosyada belgelenir.

Format [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) temel alınır;
bu proje [Semantic Versioning](https://semver.org/spec/v2.0.0.html) kurallarına uyar.

## [1.7.0] - 2026-06-29

Güvenlik ve güvenilirlik sıkılaştırma turu (inceleme sonrası remediation).

### Güvenlik
- **Kimlik doğrulama fail-closed çalışır.** `kolai_api_key` ve `kolai_secret_key` ikisi de tanımlı ve boş değilse istek kabul edilir; boş bir secret artık geçerli bir HMAC üretmek için kullanılamaz.
- **Sabit zamanlı client karşılaştırması.** Client id artık `hash_equals()` ile karşılaştırılır.
- **Tekrar (replay) koruması.** İmzalı istek `salt` değeri tek kullanımlık nonce olarak tüketilir (birebir tekrarları engeller, istemci değişikliği gerektirmez); ayrıca opsiyonel imzalı `timestamp` izin verilen zaman penceresi dışındaki eski istekleri reddeder. Geriye dönük uyumludur — bkz. [AUTH.md](AUTH.md).
- **İstek logu PII maskeleme.** Kişisel, iletişim, vergi ve ödeme alanları istek loglarından maskelenir ve kodlanmış payload boyutu sınırlandırılır; böylece log tablosu uzun ömürlü bir PII kopyası haline gelmez.

### Düzeltildi
- **İadeler artık atomik ve kurtarılabilir.** Çok kalemli iadeler sipariş bazlı, süreçler arası bir kilit altında çalışır (nesne önbelleğinde `add`, yedek olarak MySQL `GET_LOCK`); her deneme benzersiz bir idempotency kimliği taşır ve kalıcı bir işlem defteri tutulur. Başarılı uzak iadeden sonra yerel kayıt yazılamazsa işlem hemen durur ve mutabakat için işaretlenir (tekrar denemede çift iade riski yoktur); kısmi uzak başarı, atılmak yerine yerel bir WooCommerce iade kaydı olarak korunur.
- **Ödeme tamamlama yaşam döngüsü.** Başarılı ödeme artık düz bir durum değişikliği yerine `WC_Order::payment_complete()` üzerinden geçer (transaction id, `date_paid`, `woocommerce_payment_complete`).
- **Adede duyarlı kargo.** Kargo teklifleri ve sipariş kargosu, ürün başına bir adet zorlamak yerine gerçek ürün adetlerini (ve varyasyonları) dikkate alır.
- **Yalnızca aktif indirim fiyatı.** `salePrice` yalnızca indirim gerçekten aktifken (`is_on_sale()`) döner; zamanlanmış veya süresi geçmiş indirim fiyatları artık aktifmiş gibi gösterilmez.
- **Öksüz (orphan) sipariş yok.** Sipariş kabuğu oluşturulduktan sonra bir hata olursa (geçersiz kargo seçeneği, tutarı aşan indirim, …) kabuk öksüz bırakılmaz, silinir.
- **Varyasyon kırpılması görünür.** Kırpılma işareti, mapper tarafından sessizce atılmak yerine açık `variationsTruncated` / `variationsMax` metadatası olarak sunulur.

### Değiştirildi
- `/shipment-options` ürün başına opsiyonel `quantity` (ve `variationId`) kabul eder; sade id listeleri çalışmaya devam eder. Teklifin sipariş ile eşleşmesi için `/shipment-options` ve `POST /orders` isteğine aynı ürün+adet listesini gönderin — bkz. [SHIPPING.md](SHIPPING.md).
- Açık `?ids=` ürün listesi sınırlandırıldı (`MAX_IDS = 200`).
- Sipariş `discountAmount` değişmezi (toplam tam olarak indirim kadar düşer) doğrulanır ve sapma loglanır.
- Doğrudan `error_log()` çağrıları WooCommerce'in yapısal loglayıcısına taşındı.

### Erişilebilirlik & Arayüz
- Etiketler / ARIA (filtreler, açılır-kapanır butonlar, canlı durum bölgesi, `aria-busy`) ve ayar satırlarında `label_for` eklendi.
- Responsive ve RTL uyumlu yönetim CSS'i (logical property'ler, kaydırılabilir log tablosu, focus-visible).
- Gizli anahtar alanları maskelenir ve asla geri yazdırılmaz; boş bırakılırsa mevcut değer korunur.

### Uyumluluk & i18n
- Product Block Editor uyumluluğu bildirilir; WooCommerce 10.9.x / WordPress 6.9 ile test edildi.
- Kalan yönetim JavaScript metinleri yerelleştirildi; çeviri katalogları (`.pot` / `tr_TR`) 1.7.0 ile senkronlandı.

## [1.6.0]

### Eklendi
- **Vergi (KDV).** Ürün endpoint'leri, mağazanın vergi ayarlarını dikkate alarak vergi dahil `price` / `salePrice` ile birlikte `includedTax` / `taxPrice` / `taxPercentage` dökümünü döner; sipariş `discountAmount` değeri, vergi dahil toplamları tutarlı tutmak için vergiye duyarlı negatif fee olarak uygulanır.

## [1.5.0]

### Eklendi
- **iyzico iade / iptal entegrasyonu.** WooCommerce iade ve iptalleri otomatik olarak iyzico'ya iletilir: iadeler gizli `kolai-app` ödeme geçidindeki yerel "İade et" butonu (`process_refund`) üzerinden çalışır ve tutar kayıtlı `itemTransactions` kalemlerine dağıtılır; iptaller `woocommerce_order_status_cancelled` hook'unda kayıtlı `paymentId` ile yapılır.
- iyzipay-php SDK `includes/vendor/iyzipay-php/` altında bundle edildi.
- iyzico API Key / Secret Key / Ortam (sandbox–production) ayarları.
- WooCommerce HPOS ve Cart/Checkout Blocks uyumluluğu bildirildi; çeviri şablonu ve tr_TR / nl_NL çevirileri eklendi.

## [1.3.0]

### Eklendi
- `PATCH /orders/{orderId}` artık opsiyonel `paymentId` ve `itemTransactions` alanlarını sipariş meta'sı olarak kaydeder (`kolai_payment_id`, `kolai_item_transactions`).

## [1.2.0]

### Eklendi
- Yorumlar / değerlendirmeler: `GET /products/{id}/reviews` (sayfalama + status/rating/modified_after filtreleri) ve `GET /reviews/{id}`. Varsayılan olarak onaylı yorumlar döner. Yeni scope'lar `RETRIEVE_REVIEWS`, `RETRIEVE_REVIEW`; yeni hata kodları `6000-6004`. PII alanları (yazar e-posta/IP/agent) yanıttan kasıtlı olarak çıkarıldı.

## [1.1.1]

### Düzeltildi
- HTTP/2: `/products` sayfalama metadatası, proxy / HTTP-2 protocol error'larından kaçınmak için özel `X-Kolai-*` header'larından response body'ye (`pagination`) taşındı.
- DB migration: Plugin sürümü değiştiğinde (FTP/Git ile dosya güncelleme dahil) log tablosu otomatik oluşturulur — deaktive/aktive gerekmez.
- Logger guard: Tablo yoksa `Kolai_Logger::is_enabled()` false döner; yazımlar sessizce atlanır.

## [1.1.0]

### Eklendi
- DB tabanlı yapısal log altyapısı, yönetim sayfası, seviye/retention ayarları ve günlük cleanup cron.
- `/products` artık her zaman sayfalanır (`page`, `per_page`, maks. 200); daha hafif liste yanıtları, N+1 sorgularını kaldırmak için bulk cache priming / batch term fetch ve `?ids=` / `?modified_after=` filtreleri. `MAX_VARIATIONS_PER_PRODUCT = 100` tavanı ve auth, request ve servis katmanlarında yapısal log noktaları eklendi.

## [1.0.3]

- Önceki kararlı sürüm.

[1.7.0]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.7.0
[1.6.0]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.6.0
[1.5.0]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.5.0
[1.3.0]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.3.0
[1.2.0]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.2.0
[1.1.1]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.1.1
[1.1.0]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.1.0
[1.0.3]: https://github.com/iyzico/iyzipay-kolai-for-woocommerce/releases/tag/1.0.3
