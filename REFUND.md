# Kolai – iyzico Iade (Refund) & Iptal (Cancel)

Kolai ile olusturulan siparislerde, WooCommerce yonetici panelinden yapilan **iade** ve **iptal**
islemleri otomatik olarak iyzico'ya iletilir. Entegrasyon, `PATCH /orders/{orderId}` ile siparise
kaydedilen odeme bilgilerini kullanir (bkz. [ORDER.md](ORDER.md)).

## Gereken siparis meta'lari

PATCH ile asagidaki alanlar siparise yazilmis olmalidir:

- `kolai_payment_id` — iyzico `paymentId` (iptal icin).
- `kolai_item_transactions` — kalem bazli `paymentTransactionId` + `paidPrice` listesi (iade icin).

Bu alanlar yoksa iade/iptal iyzico'ya iletilemez (siparis notuna hata yazilir).

## Ayarlar

WP Admin → **Kolai → Ayarlar → iyzico Iade / Iptal Ayarlari**:

| Alan | Aciklama |
|---|---|
| iyzico API Key | iyzico merchant API anahtari |
| iyzico Secret Key | iyzico merchant secret anahtari |
| iyzico Ortam | `Sandbox (Test)` veya `Production (Canli)` |

> Bu anahtarlar, REST API kimlik dogrulamasinda kullanilan Kolai API Key/Secret'tan **ayridir**.
> Ortam secimine gore baseUrl otomatik belirlenir: sandbox → `https://sandbox-api.iyzipay.com`,
> production → `https://api.iyzipay.com`.

## Iade (Refund)

- Siparis ekranindaki WooCommerce **"Iade et"** butonu (gateway: *Kolai App (iyzico)*) kullanilir.
- Tam veya **kismi** iade desteklenir. Istenen tutar, kayitli `itemTransactions` kalemlerine sirayla
  dagitilir ve her kalem icin iyzico `paymentTransactionId` uzerinden iade cagrisi yapilir.
- Ayni siparise ardisik iadeler yapilabilir; toplam iade kalemin `paidPrice` degerini gecemez.
  Kalem bazli kumulatif iade `kolai_refunded_transactions` meta'sinda takip edilir.
- Iyzico cagrisi basarisiz olursa `process_refund` bir `WP_Error` doner; WooCommerce iadeyi geri alir
  ve hatayi yoneticiye gosterir. Her adim icin siparise not eklenir.

> **Onemli:** Yalnizca gateway uzerinden ("Iade et" butonu) yapilan iadeler iyzico'ya gider.
> "Manuel iade" secenegi yalnizca WooCommerce kaydi olusturur, iyzico'ya **iletilmez**.

## Iptal (Cancel)

- Siparis durumu **"Iptal edildi" (cancelled)** olduğunda `woocommerce_order_status_cancelled`
  hook'u ile iyzico iptali tetiklenir (`paymentId` ile, tum ödeme).
- iyzico iptali **yalnizca odeme ile ayni gun** (bankalar mutabakat almadan once) gecerlidir; ayni gun
  degilse iptal yerine **iade** kullanilmalidir.
- Sonuc `kolai_iyzico_cancel_result` meta'sina yazilir ve siparise not eklenir. Idempotenttir: iyzico
  bir kez basariyla iptal ettiyse tekrar denenmez.
- **Sinirlama:** iyzico iptali basarisiz olsa bile WooCommerce durum degisikligi engellenmez; hata
  log'a ve siparis notuna yazilir. Anahtarlar ayarli degilse islem zarifce atlanir (not + log).

## Loglama

Tum iade/iptal adimlari `payment` context'i altinda loglanir (bkz. [LOGS.md](LOGS.md)).
