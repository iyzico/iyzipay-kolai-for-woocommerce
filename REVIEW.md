# Kolai API – Yorum / Değerlendirme (Review) Endpoint'leri

Base URL: `https://your-site.com/wp-json/kolai/v1`

Genel response formatı ve hata kodları için [README.md](README.md) dosyasına bakın.

WooCommerce yorumlarını WordPress comment'leri üzerine kuruyor (`comment_type = 'review'`). 1-5 puan `commentmeta` tablosunda `rating` anahtarında, doğrulanmış müşteri ise `verified` anahtarında saklanıyor. Bu endpoint'ler bu yapıyı okuyarak hafif bir DTO döner.

## Hata Kodları (Review)

- `6000` Invalid review request
- `6001` Review not found
- `6003` Invalid rating (1-5 dışı)
- `6004` Reviews disabled (rezerv)

Ayrıca ürün bulunamazsa `2001 Product not found`, geçersiz ID için `2000 Invalid product id` döner.

## Yetkilendirme

| Endpoint | Scope |
|---|---|
| `GET /products/{id}/reviews` | `RETRIEVE_REVIEWS` |
| `GET /reviews/{id}` | `RETRIEVE_REVIEW` |

Postman pre-request script'inin bu yeni scope'ları yakalaması için aşağıdaki regex'leri eklemeniz gerekir:

```javascript
// Reviews — bir ürünün yorumları
else if (method === 'GET' && uriPath.match(/^\/wp-json\/kolai\/v1\/products\/[^/]+\/reviews$/)) {
    scope = 'RETRIEVE_REVIEWS';
}

// Tek yorum
else if (method === 'GET' && uriPath.match(/^\/wp-json\/kolai\/v1\/reviews\/[^/]+$/)) {
    scope = 'RETRIEVE_REVIEW';
}
```

(Bunları mevcut `else if` zincirinin içine, `Static routes` blokundan **önce** ekleyin.)

---

## GET /products/{id}/reviews

Bir ürünün yorumlarını **sayfalı** olarak listeler. Varsayılan olarak yalnızca **onaylı** (`approved`) yorumlar döner.

### Query parametreleri

| Param | Tip | Default | Açıklama |
|---|---|---|---|
| `page` | int | `1` | 1-tabanlı sayfa indeksi |
| `per_page` | int | `100` | Sayfa başı kayıt; **maksimum 200** |
| `status` | string | `approved` | `approved` / `hold` / `spam` / `trash` / `all` |
| `rating` | int | — | 1-5 arası tam eşleşme |
| `modified_after` | ISO-8601 | — | Bu tarihten sonra eklenen yorumlar (incremental sync) |

### Pagination

`/products` ile aynı pattern: response body envelope'u içinde `pagination` alanı:

| Alan | Açıklama |
|---|---|
| `pagination.total` | Toplam yorum sayısı |
| `pagination.totalPages` | Toplam sayfa |
| `pagination.page` | Mevcut sayfa |
| `pagination.perPage` | Mevcut sayfa boyutu |

### Request örnekleri

```
# Default: ilk sayfa, onaylı yorumlar
GET /wp-json/kolai/v1/products/23/reviews

# 200'erlik 2. sayfa, sadece 5 yıldızlılar
GET /wp-json/kolai/v1/products/23/reviews?page=2&per_page=200&rating=5

# Bekleyen (moderasyondaki) yorumlar
GET /wp-json/kolai/v1/products/23/reviews?status=hold

# Son 24 saatte eklenen
GET /wp-json/kolai/v1/products/23/reviews?modified_after=2026-05-05T00:00:00Z
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
      "id": 142,
      "productId": "23",
      "rating": 5,
      "author": "Mehmet K.",
      "content": "Hızlı kargo, kaliteli ürün.",
      "date": "2026-05-01T10:00:00+00:00",
      "status": "approved",
      "verifiedBuyer": true
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 100,
    "total": 42,
    "totalPages": 1
  }
}
```

### Response (error örnekleri)

Geçersiz ürün:

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

Geçersiz rating filtresi:

```json
{
  "status": "failure",
  "errorCode": "6003",
  "errorMessage": "rating must be between 1 and 5",
  "data": null
}
```

---

## GET /reviews/{id}

Tek bir yorumu detayıyla getirir. Yorum yoksa veya bir ürüne bağlı değilse 404 döner.

### Request

```
GET /wp-json/kolai/v1/reviews/142
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
    "id": 142,
    "productId": "23",
    "rating": 5,
    "author": "Mehmet K.",
    "content": "Hızlı kargo, kaliteli ürün.",
    "date": "2026-05-01T10:00:00+00:00",
    "status": "approved",
    "verifiedBuyer": true
  }
}
```

> **Not:** `pagination` alanı **yok** — sadece liste endpoint'inde döner.

### Response (error)

```json
{
  "status": "failure",
  "errorCode": "6001",
  "errorMessage": "Review not found",
  "data": null
}
```

---

## Alanlar

| Alan | Tip | Açıklama |
|---|---|---|
| `id` | int | Yorum (comment) ID |
| `productId` | string | Yorumun bağlı olduğu ürün ID'si |
| `rating` | int / null | 1-5; rating yoksa `null` |
| `author` | string | Görünen ad (display name) |
| `content` | string | Yorum metni (HTML kaldırılmamış — WP standartında saklanır) |
| `date` | ISO-8601 | UTC zaman |
| `status` | string | `approved` / `hold` / `spam` / `trash` |
| `verifiedBuyer` | bool | WC tarafından doğrulanmış müşteri mi? |
| `parentId` | int | (varsa) cevap verilen yorum ID'si |

## Gizlenen Alanlar (PII)

Aşağıdaki alanlar **kasıtlı olarak** API yanıtında yer almaz — KVKK/GDPR uyumu için:

- `comment_author_email`
- `comment_author_IP`
- `comment_agent` (User-Agent)
- `user_id`

Service katmanında bile bu alanlar formatlama sırasında dahil edilmez; mapper bunlara erişemez.

## Performans Notları

- WordPress'in `get_comments()` fonksiyonu kullanılıyor; ürün ID'si ile filtreleme tek sorgudur.
- Rating filtresi `meta_query` ile uygulanıyor (`commentmeta` indeksli olduğu için hızlı).
- `modified_after` filtresi `comment_date_gmt` indeksini kullanır.
- Sayfalama zorunludur (`per_page` cap = 200) — büyük mağazalarda yüz binlerce yorum birikebilir.

## Loglama

`Kolai_Logger` ile her sorgu için aşağıdaki kayıtlar `review` bağlamında yazılır (log açıksa):

- `List query started` — request parametreleri
- `List query finished` — sorgu süresi (ms), bulunan kayıt sayısı, toplam, toplam sayfa
- `Single review fetch started` / `finished`
- `Review not found` (warning), `Comment is not a review` (warning), vb.

Detay için [LOGS.md](LOGS.md).
