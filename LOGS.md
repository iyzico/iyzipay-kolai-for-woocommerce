# Kolai – Log Sistemi

Eklenti, gelen istekleri ve iç adımlari saklayan ayrı bir log altyapisi içerir. Sorun teshisi sirasinda hangi adimda patlama oldugunu görmek icin tasarlandi; **varsayilan olarak kapalidir** ve yönetici "Ayarları Kaydet" butonuyla acik konuma getirmedikçe **hicbir log yazilmaz**.

## Yönetim Sayfasi

WP Admin → **Kolai → Loglar**

### Log Ayarlari

| Ayar | Aciklama |
|---|---|
| **Log Tutmayı Etkinleştir** | Ana anahtar. İşaretli olmadıkca logger no-op (sıfıra yakin maliyet). |
| **Minimum Log Seviyesi** | `debug` / `info` / `warning` / `error`. Bu seviyenin altindaki kayitlar yazilmaz. |
| **Log Saklama Süresi (gün)** | Bu sürenin üzerindeki kayitlar günlük WP cron ile silinir. `0` = sınırsız (önerilmez). |

Kaydet butonuna basana kadar değişiklikler etkili olmaz. Reset cache hooks her save sonrasi otomatik tetiklenir.

### Log Kayitlari Bölümü

- Filtreler: seviye, bağlam (`auth`, `request`, `product`, `order`, `shipping`, `contract`), serbest metin arama
- Sayfalama (önceki / sonraki — sayfa basi 100 kayit)
- **Yenile** ve **Otomatik yenileme** (5 sn aralikla)
- **Tüm Logları Temizle** — onay isteyerek tabloyu boşaltır
- Her satirda **▶ data** butonu ile JSON payload'ı acılabilir
- Aynı isteğin tüm logları `request_id` üzerinden grupludur (rid: ... satirinda görünür)

## Seviyeler

| Seviye | Ne zaman kullanılır |
|---|---|
| `debug` | Adim adim akış izlemek (sorgu süresi, parametre çiftleri, vb.). Üretimde kapatın — bir istek yüzlerce satır üretebilir. |
| `info` | Standart akış kayıtları: request başla/bitti, ana servis adımları. Varsayılan. |
| `warning` | Beklenen ama dikkat gerektiren durumlar (geçersiz scope, ürün bulunamadı, validation reddi). |
| `error` | Beklenmeyen exception/hata. Stack trace ilk 8 frame ile birlikte. |

## Bağlamlar (context)

| Context | Nereden | Tipik kayıtlar |
|---|---|---|
| `auth` | [class-kolai-auth.php](includes/class-kolai-auth.php) | Auth basarili, scope uyumsuz, signature uyumsuz, eksik parametre |
| `request` | [class-kolai-route-base.php](includes/class-kolai-route-base.php) | Request basla/bitti, Kolai_Exception, Throwable + stack trace |
| `product` | [product-service.php](includes/product/product-service.php) | Liste sorgu/format süresi, tek ürün fetch, varyasyon trim, formatla hatasi |
| `order` | [order-service.php](includes/order/order-service.php) | Payload parse, validation, sipariş kabuk olusturuldu, total kaydedildi |
| `shipping` | [shipping-routes.php](includes/shipping/shipping-routes.php) | Shipment options istek, gecersiz body |
| `contract` | [contract-routes.php](includes/contract/contract-routes.php) | Sözlesme/aydinlatma metni istegi |

## Veritabanı

Aktivasyon sırasında `{$wpdb->prefix}kolai_logs` tablosu oluşturulur:

| Sütun | Tip | Görev |
|---|---|---|
| `id` | BIGINT PK | Otomatik |
| `created_at` | DATETIME | UTC zaman |
| `level` | VARCHAR(20) | debug/info/warning/error |
| `context` | VARCHAR(50) | auth/request/product/... |
| `request_id` | VARCHAR(40) | Aynı isteğin tüm satırları için ortak UUID |
| `method` | VARCHAR(10) | HTTP yöntemi |
| `route` | VARCHAR(255) | REST yolu |
| `message` | VARCHAR(1000) | Insan okunur özet |
| `data` | LONGTEXT | JSON payload (truncated/normalized) |
| `duration_ms` | INT | İlgili adımın süresi |

İndeksler: `created_at`, `level`, `context`, `request_id`.

Tablo plugin **deaktive edildiğinde silinmez** — geçmiş kayitlar korunur. Tamamen kaldırmak için `Kolai_Logger::drop_table()` çağrılabilir (uninstall script).

## Cron

Aktivasyonda `daily` cron olarak `kolai_logs_cleanup` planlanir. Handler `Kolai_Logger::cleanup()` retention günü ayarlı eski satirlari siler. Deaktivasyonda unschedule edilir.

## Programatik Kullanım

Eklenti içinde başka noktalardan log atmak için:

```php
Kolai_Logger::debug('product', 'Custom checkpoint', array('foo' => 'bar'));
Kolai_Logger::info('order',   'Stock reserved', array('order_id' => 123));
Kolai_Logger::warning('auth', 'Suspicious request', array('ip' => $ip));
Kolai_Logger::error('shipping', 'Carrier API down', array('exception' => $e->getMessage()));
```

`is_enabled()` kontrolü içeride yapilir; logger kapaliysa cağrı no-op'tur. Performans hassas yerlerde de cağrı maliyetinden çekinmeden kullanilabilir.

## Güvenlik Notları

- Auth context'inde **signature** ve **secret key** asla loglanmaz. Sadece `clientId`, `scope`, `uri_path`, `salt` ve eşleşip eşleşmediği yazılır.
- Order/shipping payload'ları `data` alanına JSON olarak yazılır — KVKK/GDPR uyumu için retention süresini kısa tutmaniz tavsiye edilir.
- AJAX uçları (`kolai_logs_fetch`, `kolai_logs_clear`) `manage_options` capability + nonce ile korunmaktadir.

## Sorun Giderme

**1) Tablo yok hatası**
İlk yükleme sonrası eklentiyi bir kez deaktive + tekrar aktive ederek `dbDelta` çalışmasını tetikleyin.

**2) Loglar yazılmıyor**
- Loglar sayfasında "Log Tutmayı Etkinleştir" işaretli mi?
- "Ayarları Kaydet" butonuna bastınız mı?
- İstek hangi seviye atıyor? Min seviyeyi `debug`a çekip tekrar deneyin.

**3) Cron temizlemiyor**
- WP cron çalışıyor mu? `wp cron event list` ile `kolai_logs_cleanup` planlandı mı kontrol edin.
- Retention günü `0` ise cleanup hiç çalışmaz.
