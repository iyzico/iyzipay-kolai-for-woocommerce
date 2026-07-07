# Kolai API – Sipariş (Order) Endpoint'leri

Base URL: `https://your-site.com/wp-json/kolai/v1`

Genel response formatı ve hata kodları için [README.md](README.md) dosyasına bakın.

## Hata Kodları (Order)

- `4000` Invalid order request
- `4001` Invalid shipment option
- `4002` Insufficient stock
- `4003` Discount exceeds total

---

## GET /order-types

WooCommerce siparis durumlarini (order status) key-value olarak dondurur. PATCH `/orders/{orderId}` ile siparis guncellerken `orderStatus` alaninda kullanilacak gecerli degerler bu endpoint'ten alinabilir.

### Request

```
GET /wp-json/kolai/v1/order-types
```

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": {
    "pending": "Pending payment",
    "processing": "Processing",
    "on-hold": "On hold",
    "completed": "Completed",
    "cancelled": "Cancelled",
    "refunded": "Refunded",
    "failed": "Failed"
  }
}
```

Not: `data` icindeki anahtarlar (ornegin `pending`, `processing`) siparis durumu slug'laridir; PATCH `/orders/{orderId}` body'deki `orderStatus` alaninda bu degerler kullanilmalidir.

---

## POST /orders

Dis sistemden siparis olusturur. Stok kontrolu zorunludur. Siparis **pending payment** (odeme bekliyor) olarak olusturulur; odeme tamamlanana kadar stok dusulmez. Siparisin gecerlilik suresi WooCommerce **Hold stock (minutes)** ayarindan okunur ve yanit icinde `orderExpireAt` olarak ISO 8601 formatinda dondurulur.

### Request

```
POST /wp-json/kolai/v1/orders
```

```json
{
  "buyer": {
    "email": "john@doe.com",
    "firstName": "John",
    "lastName": "Doe",
    "phone": "+90 555 000 00 00"
  },
  "billingAddress": {
    "countryId": "TR",
    "cityId": "34",
    "town": "Kadikoy",
    "postcode": "34710",
    "addressLine": "Ornek Mah. 1. Sok. No: 2",
    "invoiceType": "company",
    "companyName": "Ornek Ltd. Sti.",
    "taxId": "1234567890",
    "taxOffice": "Kadikoy"
  },
  "shippingAddress": {
    "countryId": "TR",
    "cityId": "34",
    "town": "Kadikoy",
    "postcode": "34710",
    "addressLine": "Ornek Mah. 1. Sok. No: 2"
  },
  "products": [
    { "productId": 66, "quantity": 2 },
    { "productId": 12, "quantity": 1 }
  ],
  "shipmentOptionId": "flat_rate:2",
  "discountAmount": 25.0
}
```

Not: `discountAmount` opsiyoneldir. Gonderildiginde `0.00` dan buyuk olmalidir.

Not: Adres alanlari (hem `billingAddress` hem `shippingAddress` icin ayni):
- `countryId`: ulke kodu (`TR`). Zorunlu.
- `cityId`: **il** plaka kodu; WooCommerce `state` alanina yazilir (`06` -> `TR06`). Zorunlu. Tek haneli plakalar da kabul edilir (`6` -> `TR06`).
- `town`: **ilce** adi; WooCommerce `city` alanina yazilir (`Cankaya`). (iyzico bu alani `town` olarak gonderir; geriye donuk uyum icin eski `district` / `districtId` adlari da kabul edilir — ilki dolu olan kullanilir.) Hicbiri gonderilmezse `city`, `cityId`'den turetilen **il adina** duser (`TR01` -> `Adana`), bos kalmaz.
- `addressLine`: **sadece acik adres** (mahalle / sokak / no / daire). WooCommerce `address_1` alanina **oldugu gibi** yazilir; icine il/ilce **konmamalidir**, yoksa adreste tekrar eder.
- `postcode`: posta kodu (opsiyonel).

> **Onemli:** iyzico "Pay with iyzico" (PWI) adresinde ilce `town` alaninda gelir; caller bunu oldugu gibi iletmeli ve `addressLine`'i il/ilce icermeyecek sekilde temiz gondermelidir.

Yanlis (city bos kalir, `address_1` il/ilce ile kirlenir):

```json
{
  "cityId": "06",
  "addressLine": "Akpinar Mah. Cankaya sokak 1 No: 2 Daire: 3 Kat: Cankaya Ankara /"
}
```

Dogru (beklenen):

```json
{
  "countryId": "TR",
  "cityId": "06",
  "town": "Cankaya",
  "postcode": "06550",
  "addressLine": "Akpinar Mah. Cankaya sokak 1 No: 2 Daire: 3 Kat"
}
```

Sonuc: `state` = `TR06`, `city` = `Cankaya`, `address_1` = `Akpinar Mah. Cankaya sokak 1 No: 2 Daire: 3 Kat`.

Not: `billingAddress` icinde opsiyonel fatura alanlari:
- `invoiceType`: `personal` veya `company` (varsayilan `personal`)
- `companyName`, `taxId`, `taxOffice` alanlari opsiyoneldir
- `taxId`: VKN (10 hane) veya TCKN (11 hane). `""` veya `null` gelebilir
- `invoiceType=company` ise `taxId` girildiginde 10 hane olmalidir
- `invoiceType=personal` ise `taxId` girildiginde 11 hane olmalidir

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": {
    "orderId": 1234,
    "orderNumber": "1234",
    "status": "pending",
    "total": 525.0,
    "currency": "TRY",
    "paymentMethod": "kolai-app",
    "orderExpireAt": "2026-02-04T10:15:30+00:00"
  }
}
```

Not: `orderExpireAt`, WooCommerce **Hold stock (for unpaid orders)** ayarindaki sure (dakika) kullanilarak hesaplanir; bu tarihe kadar odeme alinmazsa siparis iptal edilir. Format her zaman UTC icin `YYYY-MM-DDTHH:mm:ss+00:00` (ISO 8601) seklindedir.

### Response (insufficient stock)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "4002",
  "errorMessage": "Insufficient stock quantity",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

### Response (invalid shipment option)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "4001",
  "errorMessage": "Invalid shipment option",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

### Response (discount exceeds total)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "4003",
  "errorMessage": "Discount exceeds order total",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

---

## GET /orders/{orderId}

Belirli bir siparisi ID ile dondurur.

### Request

```
GET /wp-json/kolai/v1/orders/{orderId}
```

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": {
    "orderId": 1234,
    "orderNumber": "1234",
    "status": "pending",
    "total": 525.0,
    "currency": "TRY",
    "paymentMethod": "kolai-app",
    "orderExpireAt": "2026-02-04T10:15:30+00:00",
    "dateCreated": "2026-02-04T10:10:00+00:00",
    "dateModified": "2026-02-04T10:10:00+00:00"
  }
}
```

Not: `orderExpireAt` hesaplamasi siparisin olusturulma zamanina (`dateCreated`) ve WooCommerce **Hold stock (for unpaid orders)** ayarina gore yapilir.

### Response (order not found)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "1001",
  "errorMessage": "Order not found",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

---

## PATCH /orders/{orderId}

Mevcut bir siparisin durumunu guncellemek icin kullanilir.

### Request

```
PATCH /wp-json/kolai/v1/orders/{orderId}
```

```json
{
  "orderStatus": "processing"
}
```

Not: `orderStatus` alaninin degeri, `/wp-json/kolai/v1/order-types` endpoint'inden donen status anahtarlarindan biri olmalidir (ornegin `pending`, `processing`, `completed`, `cancelled`).

Opsiyonel olarak odeme bilgileri de gonderilebilir. Gonderildiginde bu alanlar siparise **order meta** olarak kaydedilir:

```json
{
  "orderStatus": "processing",
  "paymentId": "123456789",
  "price": 100.00,
  "paidPrice": 110.00,
  "installment": 3,
  "itemTransactions": [
    {
      "itemId": "7ab0ee59-18b4-4e30-9ca8-04197bb34127",
      "paymentTransactionId": "34426266",
      "transactionStatus": 2,
      "price": 10.00,
      "paidPrice": 10.00
    }
  ]
}
```

Not: Odeme alanlari opsiyoneldir ve `orderStatus` ile birlikte gonderilebilir.
- `paymentId`: `kolai_payment_id` order meta'sina kaydedilir.
- `itemTransactions`: normalize edilerek JSON olarak `kolai_item_transactions` order meta'sina kaydedilir. Her bir kalem `itemId`, `paymentTransactionId`, `transactionStatus`, `price`, `paidPrice` alanlarini icerir.
- `paidPrice` / `installment`: gonderildiginde siparisin toplami gercekten tahsil edilen `paidPrice` ile eslenir. `paidPrice` ile siparisin o anki toplami arasindaki fark bir fee satiri olarak eklenir:
  - Fark **pozitif** ise **Vade Farki** (taksit komisyonu) olarak, **negatif** ise **Indirim** olarak kaydedilir. Fark KDV dahil (brüt) kabul edilir ve mevcut vergi oranlarina orantili dagitilir.
  - `installment` (> 1) `kolai_installment_count`, fark tutari ise `kolai_installment_fee` order meta'sina yazilir. Bu iki anahtar **Ayarlar › Meta Alan Anahtarlari** altindan degistirilebilir (bkz. [CONTRACT/meta ayarlari]).
  - Idempotent: ayni `paidPrice` ile tekrar gonderildiginde onceki vade farki satiri silinip yeniden hesaplanir; fee'ler ustuste eklenmez.

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": {
    "orderId": 1234,
    "orderNumber": "1234",
    "status": "processing",
    "total": 525.0,
    "currency": "TRY",
    "paymentMethod": "kolai-app",
    "orderExpireAt": "2026-02-04T10:15:30+00:00",
    "dateCreated": "2026-02-04T10:10:00+00:00",
    "dateModified": "2026-02-04T10:16:00+00:00"
  }
}
```

### Response (invalid orderStatus)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "4000",
  "errorMessage": "Invalid orderStatus: foo",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```
