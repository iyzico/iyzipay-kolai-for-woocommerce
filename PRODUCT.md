# Kolai API – Ürün (Product) Endpoint'leri

Base URL: `https://your-site.com/wp-json/kolai/v1`

Genel response formatı ve hata kodları için [README.md](README.md) dosyasına bakın.

## Hata Kodları (Product)

- `2000` Invalid product id
- `2001` Product not found
- `2002` Product not visible
- `2003` Variation parent not found
- `2004` Invalid product list

---

## GET /products

Tum urunleri listeler.

### Request

```
GET /wp-json/kolai/v1/products
```

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
  "data": [
    {
      "id": 12,
      "name": "T-Shirt",
      "slug": "t-shirt",
      "type": "simple",
      "status": "publish",
      "featured": false,
      "catalog_visibility": "visible",
      "description": "...",
      "short_description": "...",
      "sku": "TS-001",
      "menu_order": 0,
      "virtual": false,
      "permalink": "https://your-site.com/product/t-shirt",
      "date_created": "2026-01-12T09:12:00+00:00",
      "date_modified": "2026-01-15T09:12:00+00:00",
      "price": 100,
      "regular_price": 120,
      "sale_price": 100,
      "date_on_sale_from": null,
      "date_on_sale_to": null,
      "total_sales": 5,
      "tax_status": "taxable",
      "tax_class": "",
      "sold_individually": false,
      "purchase_note": "",
      "shipping_class_id": 0,
      "inStock": true,
      "weight": 0.5,
      "dimensions": { "length": 10, "width": 20, "height": 2 },
      "upsell_ids": [],
      "cross_sell_ids": [],
      "parent_id": 0,
      "attributes": [],
      "default_attributes": [],
      "variations": [
        {
          "id": 101,
          "sku": "TS-001-RED-M",
          "description": "Kirmizi / M beden",
          "price": 100,
          "sale_price": 100,
          "inStock": true,
          "attributes": [
            { "id": 11, "name": "Renk", "slug": "pa_color", "value": "Kirmizi" },
            { "id": 22, "name": "Beden", "slug": "pa_size", "value": "M" }
          ],
          "image": { "id": 10, "url": "https://...", "alt": "" }
        }
      ],
      "tags": [],
      "downloadable": false,
      "downloads": [],
      "download_limit": -1,
      "download_expiry": -1,
      "image": { "id": 1, "url": "https://...", "alt": "" },
      "gallery": [],
      "reviews_allowed": true,
      "rating_counts": [],
      "average_rating": "0",
      "review_count": 0
    }
  ]
}
```

---

## GET /products/{id}

Tek urun getirir. Var olan bir urun degilse hata doner.

### Request

```
GET /wp-json/kolai/v1/products/12
```

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
    "id": 12,
    "name": "T-Shirt",
    "slug": "t-shirt",
    "type": "simple",
    "status": "publish",
    "featured": false,
    "catalog_visibility": "visible",
    "description": "...",
    "short_description": "...",
    "sku": "TS-001",
    "menu_order": 0,
    "virtual": false,
    "permalink": "https://your-site.com/product/t-shirt",
    "date_created": "2026-01-12T09:12:00+00:00",
    "date_modified": "2026-01-15T09:12:00+00:00",
    "price": 100,
    "regular_price": 120,
    "sale_price": 100,
    "date_on_sale_from": null,
    "date_on_sale_to": null,
    "total_sales": 5,
    "tax_status": "taxable",
    "tax_class": "",
    "sold_individually": false,
    "purchase_note": "",
    "shipping_class_id": 0,
    "inStock": true,
    "weight": 0.5,
    "dimensions": { "length": 10, "width": 20, "height": 2 },
    "upsell_ids": [],
    "cross_sell_ids": [],
    "parent_id": 0,
    "attributes": [],
    "default_attributes": [],
    "variations": [
      {
        "id": 101,
        "sku": "TS-001-RED-M",
        "description": "Kirmizi / M beden",
        "price": 100,
        "sale_price": 100,
        "inStock": true,
        "attributes": [
          { "id": 11, "name": "Renk", "slug": "pa_color", "value": "Kirmizi" },
          { "id": 22, "name": "Beden", "slug": "pa_size", "value": "M" }
        ],
        "image": { "id": 10, "url": "https://...", "alt": "" }
      }
    ],
    "tags": [],
    "downloadable": false,
    "downloads": [],
    "download_limit": -1,
    "download_expiry": -1,
    "image": { "id": 1, "url": "https://...", "alt": "" },
    "gallery": [],
    "reviews_allowed": true,
    "rating_counts": [],
    "average_rating": "0",
    "review_count": 0
  }
}
```

### Response (error example)

```json
{
  "status": "failure",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": "2001",
  "errorMessage": "Product not found",
  "woocommerceVersion": "9.1.0",
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": null
}
```

---

## GET /products-with-variants/{id}

Urun veya varyasyon ID'si ile cagrilir. ID bir **varyasyon** (child) ise, ilgili **parent** urun tum varyasyonlariyla birlikte dondurulur; parent ID ise urun oldugu gibi dondurulur. Detay icin [README](README.md) veya uygulama koduna bakin.

### Request

```
GET /wp-json/kolai/v1/products-with-variants/12
```

Response yapisi GET /products/{id} ile ayni formattadir.
