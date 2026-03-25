# Kolai Plugin

Kolai API entegrasyonu icin WordPress plugin'i.

## API Genel

Base URL: `https://your-site.com/wp-json/kolai/v1`

Tumu JSON request/response kullanir.

### Base Response

Tum endpoint'ler asagidaki formatta doner:

```json
{
  "status": "success",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "9.1.0",
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": {}
}
```

`status` degeri:
- `success`: HTTP status < 400
- `failure`: HTTP status >= 400

### Error Codes

#### 1xxx - Kolai Plugin Errors
- `1000` Internal error
- `1001` Bad request
- `1002` Not found
- `1003` Service unavailable
- `1004` WooCommerce inactive

#### 2xxx - Product Errors
- `2000` Invalid product id
- `2001` Product not found
- `2002` Product not visible
- `2003` Variation parent not found
- `2004` Invalid product list

#### 3xxx - Shipping Errors
- `3000` Invalid address
- `3001` No shipping options

#### 4xxx - Order Errors
- `4000` Invalid order request
- `4001` Invalid shipment option
- `4002` Insufficient stock
- `4003` Discount exceeds total

#### 5xxx - Contract Errors
- `5000` Invalid contract type
- `5001` Invalid contract request
- `5002` Contract not found

## Endpoints

Detayli istek/yanit ornekleri ve aciklamalar icin ilgili dokümana gidin:

| Alan       | Doküman      | Özet |
|-----------|--------------|------|
| **Kimlik Dogrulama** | [AUTH.md](AUTH.md) | HMAC-SHA256 imza dogrulamasi, scope eslemesi |
| **Ürün**  | [PRODUCT.md](PRODUCT.md)  | `GET /products`, `GET /products/{id}`, `GET /products-with-variants/{id}` |
| **Kargo** | [SHIPPING.md](SHIPPING.md) | `POST /shipment-options` (alias: `POST /shipping-options`) |
| **Sipariş** | [ORDER.md](ORDER.md)   | `GET /order-types`, `POST /orders`, `GET /orders/{orderId}`, `PATCH /orders/{orderId}` |
| **Sözleşme** | [CONTRACT.md](CONTRACT.md) | `POST /contracts` |

## Yapı

```
kolai/
├── admin/
│   ├── class-kolai-admin.php
│   ├── class-kolai-settings.php
│   ├── css/
│   │   └── kolai-admin.css
│   ├── js/
│   │   └── kolai-admin.js
│   └── views/
│       ├── contracts-page.php
│       └── settings-page.php
├── includes/
│   ├── class-kolai-activator.php
│   ├── class-kolai-api.php
│   ├── class-kolai-core.php
│   ├── class-kolai-deactivator.php
│   ├── class-kolai-exceptions.php
│   ├── class-kolai-constants.php
│   ├── class-kolai-loader.php
│   ├── class-kolai-response.php
│   ├── class-kolai-route-base.php
│   ├── contract/
│   │   ├── contract-routes.php
│   │   └── contract-service.php
│   ├── product/
│   │   ├── product-mapper.php
│   │   ├── product-routes.php
│   │   └── product-service.php
│   ├── order/
│   │   ├── order-routes.php
│   │   └── order-service.php
│   └── shipping/
│       ├── shipping-routes.php
│       └── shipping-service.php
└── kolai.php
```
