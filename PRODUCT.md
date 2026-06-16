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

## Vergi Alanları (Tax)

Tüm ürün/varyasyon yanıtlarında fiyatlar **vergi dahil** döner ve vergi kırılımı ayrı alanlarla verilir. Hesaplama WooCommerce'in kendi `wc_get_price_including_tax()` / `wc_get_price_excluding_tax()` fonksiyonlarıyla yapılır; bu sayede mağazanın **"Fiyatlara KDV dahil"** ayarı ve tanımlı vergi oranları olduğu gibi dikkate alınır.

| Alan | Tip | Açıklama |
|---|---|---|
| `price` | string | Vergi **dahil** normal fiyat |
| `salePrice` | string | Vergi **dahil** indirimli fiyat (yalnızca indirim varken döner) |
| `includedTax` | bool | Ürün vergilendirilebilir (`tax_status = taxable` ve vergi açık) ise `true` |
| `taxPrice` | string | **Efektif fiyat** üzerindeki vergi tutarı (indirim varsa `salePrice`, yoksa `price`) |
| `taxPercentage` | number | Efektif vergi oranı (%); `taxPrice / net_efektif_fiyat` üzerinden türetilir |

> **Notlar**
> - Ürün **vergilendirilemez** ise: `includedTax = false`, `taxPrice = "0.00"`, `taxPercentage = 0` döner ve `price`/`salePrice` net değerleriyle kalır.
> - `taxPrice` her zaman müşterinin ödediği **efektif** fiyatın (indirim varsa indirimli) vergisidir; `price` ve `salePrice` için ayrı vergi tutarı dönmez.
> - Net (vergi hariç) değer gerekirse `salePrice - taxPrice` (veya indirim yokken `price - taxPrice`) ile hesaplanabilir.

---

## GET /products

Ürünleri **sayfalanmış** olarak listeler. Büyük katalog için tek istekte tüm ürünleri çekmeye çalışmayın — endpoint zorunlu olarak sayfalama uygular.

### Query parametreleri

| Param | Tip | Default | Açıklama |
|---|---|---|---|
| `page` | int | `1` | 1-tabanlı sayfa indeksi |
| `per_page` | int | `100` | Sayfa başı kayıt; **maksimum 200** |
| `ids` | csv int[] | — | Sadece belirli ID'leri getir; pagination atlanır |
| `modified_after` | ISO-8601 | — | Bu tarihten sonra değiştirilen ürünler (incremental sync) |

### Pagination Metadatası (Body)

Pagination bilgisi response **body** envelope'ı içinde, `data` dizisinin yanında ayrı bir `pagination` alanı olarak döner. Daha önce HTTP header'lar denenmişti ancak bazı proxy yığınları (Cloudflare strict, HTTP/2) `Header field must only have a single value` hatası ürettiği için body'ye taşındı.

| Alan | Açıklama |
|---|---|
| `pagination.total` | Filtreye uyan toplam ürün sayısı |
| `pagination.totalPages` | Toplam sayfa sayısı |
| `pagination.page` | Mevcut sayfa |
| `pagination.perPage` | Mevcut sayfa boyutu |

### Liste Ürün Şeması (Lite)

Liste yanıtında her ürün **özet** alanları içerir. `attributes`, `variations`, `gallery`, `downloads`, `tags`, `categories` gibi ağır alanlar liste yanıtına dahil değildir — bunlar için `GET /products/{id}` veya `GET /products-with-variants/{id}` çağrılır.

Mapper aracılığıyla aşağıdaki alanlar (mevcutsa) döner:

```
id, title, link, imageLink, inStock, currency, price, salePrice,
includedTax, taxPrice, taxPercentage,
salePriceEffectiveDate, productType, gtin, mpn, itemGroupId,
productLength, productWidth, productHeight, productWeight
```

### Request örnekleri

```
# İlk sayfa (default 100 ürün)
GET /wp-json/kolai/v1/products

# 200'er ürünlük 3. sayfa
GET /wp-json/kolai/v1/products?page=3&per_page=200

# Belirli ID'leri batch olarak çek
GET /wp-json/kolai/v1/products?ids=12,34,56

# Son 24 saatte değişenler
GET /wp-json/kolai/v1/products?modified_after=2026-05-05T00:00:00Z
```

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-05-06T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "10.7.0",
  "wordpressVersion": "6.9.4",
  "phpVersion": "8.2.4",
  "data": [
    {
      "id": "12",
      "title": "T-Shirt",
      "link": "https://your-site.com/product/t-shirt",
      "imageLink": "https://.../t-shirt.jpg",
      "inStock": true,
      "currency": "TRY",
      "price": "120.00",
      "salePrice": "106.80",
      "includedTax": true,
      "taxPrice": "17.80",
      "taxPercentage": 20,
      "productType": "simple",
      "gtin": "TS-001",
      "mpn": "TS-001",
      "productWeight": "0.5",
      "productLength": "10",
      "productWidth": "20",
      "productHeight": "2"
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 200,
    "total": 12000,
    "totalPages": 60
  }
}
```

> Not: `pagination` alanı yalnızca `GET /products` listesinde döner. Tek ürün endpoint'lerinde (`/products/{id}`, `/products-with-variants/{id}`) bulunmaz.

### Senkronizasyon Önerisi

12K+ ürünlü katalogda en verimli senkronizasyon akışı:

1. İlk full sync için sayfa sayfa dolaş — `data.pagination.totalPages` toplam sayfa sayısını verir:
   ```
   GET /products?page=1&per_page=200   → response.pagination.total / totalPages oku
   GET /products?page=2&per_page=200
   ...
   ```
2. Detay gerekiyorsa her ürün için:
   ```
   GET /products/{id}
   ```
3. Sonraki sync'lerde:
   ```
   GET /products?modified_after={son_sync_zamani}
   ```

---

## GET /products/{id}

Tek ürünü **tüm detaylarıyla** (attributes, variations, gallery, kategoriler, etiketler dahil) getirir. Var olmayan ürün için 404 döner.

### Request

```
GET /wp-json/kolai/v1/products/12
```

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-05-06T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "10.7.0",
  "wordpressVersion": "6.9.4",
  "phpVersion": "8.2.4",
  "data": {
    "id": "12",
    "title": "T-Shirt",
    "description": "...",
    "link": "https://your-site.com/product/t-shirt",
    "imageLink": "https://.../t-shirt.jpg",
    "additionalImageLinks": ["https://.../1.jpg", "https://.../2.jpg"],
    "inStock": true,
    "currency": "TRY",
    "price": "120.00",
    "salePrice": "106.80",
    "includedTax": true,
    "taxPrice": "17.80",
    "taxPercentage": 20,
    "salePriceEffectiveDate": "2026-05-01T00:00:00+00:00/2026-05-31T23:59:59+00:00",
    "productType": "variable",
    "gtin": "TS-001",
    "mpn": "TS-001",
    "productWeight": "0.5",
    "productLength": "10",
    "productWidth": "20",
    "productHeight": "2",
    "attributes": [
      {
        "name": "Renk",
        "slug": "pa_color",
        "type": "taxonomy",
        "visible": true,
        "options": [
          { "id": 11, "name": "Kirmizi", "slug": "kirmizi" }
        ]
      }
    ],
    "variations": [
      {
        "id": 101,
        "sku": "TS-001-RED-M",
        "description": "Kirmizi / M beden",
        "price": "120.00",
        "salePrice": "106.80",
        "includedTax": true,
        "taxPrice": "17.80",
        "taxPercentage": 20,
        "inStock": true,
        "attributes": [
          { "id": 11, "name": "Renk", "slug": "pa_color", "value": "Kirmizi" },
          { "id": 22, "name": "Beden", "slug": "pa_size", "value": "M" }
        ],
        "image": { "id": 10, "url": "https://...", "alt": "" }
      }
    ]
  }
}
```

> **Notlar**
> - Variable ürünlerde **maksimum 100 varyasyon** dönülür. Üstündeki varyasyonlu üründe son satıra `{ "_truncated": true, "_max": 100 }` markörü eklenir; `warning` seviyesinde log üretilir.
> - Ürün bir **varyasyon** ID'si ile çağrıldıysa parent ürüne yönlendirme yapılmaz; bunun için `/products-with-variants/{id}` kullanın.

### Response (error example)

```json
{
  "status": "failure",
  "systemTime": "2026-05-06T10:15:30+00:00",
  "errorCode": "2001",
  "errorMessage": "Product not found",
  "woocommerceVersion": "10.7.0",
  "wordpressVersion": "6.9.4",
  "phpVersion": "8.2.4",
  "data": null
}
```

---

## GET /products-with-variants/{id}

Ürün veya varyasyon ID'si ile çağrılır. ID bir **varyasyon** (child) ise, ilgili **parent** ürün tüm varyasyonlarıyla birlikte döndürülür; parent ID ise ürün olduğu gibi döndürülür.

### Request

```
GET /wp-json/kolai/v1/products-with-variants/12
```

Response yapısı `GET /products/{id}` ile aynı formattadır.

---

## Performans Notları

`/products` endpoint'i 12K+ ürünlü kataloglar için aşağıdaki optimizasyonları uygular:

- **Bulk cache priming**: Sayfada dönen tüm ürünler için `_prime_post_caches`, `update_object_term_cache`, `update_meta_cache` tek seferde çağrılarak N+1 desenli sorgu çoğalması önlenir.
- **Hard limit `per_page=200`**: PHP timeout / OOM riskini sınırlar.
- **Lite formatter**: Liste yanıtında attribute/variation/gallery/downloads/categories/tags **tamamen** atlanır.
- **Term'ler batch çekilir**: `get_terms(include => [...])` ile tek sorguda alınır.
- **Variation tavanı**: `MAX_VARIATIONS_PER_PRODUCT = 100`.

Performans veya hata teşhisi için **Kolai → Loglar** sayfasından `product` bağlamındaki kayıtları izleyin (her sorgunun süresi `duration_ms` ile birlikte yazılır). Detaylar için [LOGS.md](LOGS.md).
