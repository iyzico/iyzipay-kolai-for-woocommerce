# Kolai API – Kargo (Shipping) Endpoint'leri

Base URL: `https://your-site.com/wp-json/kolai/v1`

Genel response formatı ve hata kodları için [README.md](README.md) dosyasına bakın.

## Hata Kodları (Shipping)

- `3000` Invalid address
- `3001` No shipping options

---

## POST /shipment-options

Alias: `POST /shipping-options`

Urun listesine ve adrese gore uygun kargo seceneklerini ve fiyatlarini doner.

### Request

```
POST /wp-json/kolai/v1/shipment-options
```

```json
{
  "products": [12, 34, 56],
  "address": {
    "countryId": "TR",
    "cityId": "34"
  }
}
```

**Adet (quantity) destegi (tavsiye edilir).** `products` icinde sade urun id'leri yerine adet ve varyasyon bilgisini iceren nesneler de gonderebilirsiniz. Sade id listesi geriye donuk uyumlu olarak desteklenir (adet = 1 varsayilir):

```json
{
  "products": [
    { "productId": 12, "quantity": 2 },
    { "productId": 34, "variationId": 41, "quantity": 1 }
  ],
  "address": { "countryId": "TR", "cityId": "34" }
}
```

> Kargo fiyatinin siparis olusturma ile birebir eslesmesi icin, `/shipment-options` istegine `POST /orders` ile **ayni urun + adet** listesini gonderin. Adet gonderilmezse hem teklif hem siparis 1 adet uzerinden hesaplanir (eski davranis).

Adres alanlari WooCommerce tarafinda su sekilde map edilir:
- `countryId` -> `country`
- `cityId` -> `state` (il/province). TR icin numeric plaka degerleri iki haneli sifir dolgulu koda normalize edilir (`34` -> `TR34`, `6` -> `TR06`).

> Not: Bu endpoint kargo teklifi icin yalnizca `countryId` + `cityId` (il) kullanir; ilce (`districtId`) ve acik adres (`addressLine`) gerekmez. Tam adres alanlari ve ilce/il ayrimi icin `POST /orders` (ORDER.md) sozlesmesine bakin.

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "9.1.0",
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": {
    "options": [
      {
        "id": "flat_rate:1",
        "label": "Flat Rate",
        "methodId": "flat_rate",
        "cost": 10,
        "tax": 1.8,
        "price": 11.8
      }
    ]
  }
}
```

### Response (no shipping options)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "3001",
  "errorMessage": "No shipping options available",
  "woocommerceVersion": "9.1.0",
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": null
}
```

### Response (invalid address)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "3000",
  "errorMessage": "countryId and cityId are required",
  "woocommerceVersion": "9.1.0",
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": null
}
```

### Response (invalid product list)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "2004",
  "errorMessage": "Products list is required",
  "woocommerceVersion": "9.1.0",
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": null
}
```

### Response (WooCommerce inactive)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "1004",
  "errorMessage": "WooCommerce is not active",
  "woocommerceVersion": null,
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": null
}
```
