# Kolai API – Sozlesme (Contract) Endpoint'leri

Base URL: `https://your-site.com/wp-json/kolai/v1`

Genel response formati ve hata kodlari icin [README.md](README.md) dosyasina bakin.

## Hata Kodlari (Contract)

- `5000` Invalid contract type
- `5001` Invalid contract request
- `5002` Contract not found

---

## POST /contracts

Belirtilen sozlesme sablonunu satici bilgileri doldurulmus sekilde dondurur. Alici, siparis ve kargo bilgileri icin `{{placeholder}}` formati korunur — bu alanlari doldurmak istemci (mobil) tarafin sorumlulugundadir.

### Request

```
POST /wp-json/kolai/v1/contracts
```

```json
{
  "type": "distance_sales"
}
```

| Alan | Tip | Zorunlu | Aciklama |
|------|-----|---------|----------|
| `type` | string | Evet | Sozlesme tipi: `distance_sales` veya `preliminary_info` |

### Sozlesme Tipleri

| Tip | Aciklama |
|-----|----------|
| `distance_sales` | Mesafeli Satis Sozlesmesi |
| `preliminary_info` | On Bilgilendirme Formu |

### Response (success)

```json
{
  "status": "success",
  "systemTime": "2026-03-25T12:00:00+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": {
    "type": "distance_sales",
    "title": "Mesafeli Satis Sozlesmesi",
    "content": "<h1>Mesafeli Satis Sozlesmesi</h1><h2>MADDE 1 - TARAFLAR</h2><h3>1.1 SATICI</h3><p><strong>Unvan:</strong> Ornek Magaza<br>...<h3>1.2 ALICI</h3><p><strong>Ad Soyad:</strong> {{buyer_name}}<br>...",
    "placeholders": {
      "{{buyer_name}}": "Alici adi",
      "{{buyer_email}}": "Alici e-posta adresi",
      "{{buyer_phone}}": "Alici telefonu",
      "{{buyer_address}}": "Alici adresi",
      "{{order_date}}": "Siparis tarihi",
      "{{order_number}}": "Siparis numarasi",
      "{{order_total}}": "Siparis toplami",
      "{{order_currency}}": "Para birimi",
      "{{payment_method}}": "Odeme yontemi",
      "{{shipping_method}}": "Kargo yontemi",
      "{{shipping_cost}}": "Kargo ucreti",
      "{{product_list}}": "Urun listesi (HTML tablo)",
      "{{delivery_date}}": "Tahmini teslim tarihi",
      "{{right_of_withdrawal_period}}": "Cayma hakki suresi"
    }
  }
}
```

`content` icindeki satici bilgileri (`{{seller_*}}`) WP/WC ayarlarindan otomatik doldurulur. Geri kalan placeholder'lar `{{key}}` formatinda kalir ve `placeholders` nesnesinde listelenir. Istemci taraf bu placeholder'lari kendi elindeki verilerle (alici, siparis, sepet) replace ederek sozlesmeyi render eder.

### Response (invalid contract type)

```json
{
  "status": "failure",
  "systemTime": "2026-03-25T12:00:00+00:00",
  "errorCode": "5000",
  "errorMessage": "Invalid contract type: unknown",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

### Response (missing type)

```json
{
  "status": "failure",
  "systemTime": "2026-03-25T12:00:00+00:00",
  "errorCode": "5001",
  "errorMessage": "Missing required field: type",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

---

## Yer Tutucular (Placeholders)

Sablonlarda `{{placeholder}}` formati kullanilir. Satici placeholder'lari sunucu tarafinda otomatik doldurulur; geri kalanlari istemci tarafinda doldurulur.

### Satici Bilgileri (sunucu tarafinda doldurulur)

| Yer Tutucu | Kaynak |
|------------|--------|
| `{{seller_name}}` | `get_option('blogname')` |
| `{{seller_address}}` | WooCommerce magaza adres ayarlari |
| `{{seller_phone}}` | `get_option('woocommerce_store_phone')` |
| `{{seller_email}}` | `get_option('admin_email')` |
| `{{seller_tax_id}}` | `get_option('kolai_seller_tax_id')` |
| `{{seller_mersis_no}}` | `get_option('kolai_seller_mersis_no')` |

### Alici / Siparis Bilgileri (istemci tarafinda doldurulur)

| Yer Tutucu | Aciklama |
|------------|----------|
| `{{buyer_name}}` | Alici adi |
| `{{buyer_email}}` | Alici e-posta adresi |
| `{{buyer_phone}}` | Alici telefonu |
| `{{buyer_address}}` | Alici adresi |
| `{{order_date}}` | Siparis tarihi |
| `{{order_number}}` | Siparis numarasi |
| `{{order_total}}` | Siparis toplami |
| `{{order_currency}}` | Para birimi |
| `{{payment_method}}` | Odeme yontemi |
| `{{shipping_method}}` | Kargo yontemi |
| `{{shipping_cost}}` | Kargo ucreti |
| `{{product_list}}` | Urun listesi (HTML tablo olarak istemci olusturur) |
| `{{delivery_date}}` | Tahmini teslim tarihi |
| `{{right_of_withdrawal_period}}` | Cayma hakki suresi (varsayilan: "14 gun") |

---

## Yonetici Paneli

WP Admin > Kolai > Sozlesmeler sayfasindan:

- **Satici VKN ve MERSIS numarasi** girilir
- Her iki sozlesme sablonu **wp_editor** ile duzenlenebilir
- Yer tutucu referans paneli acilir/kapanir seklinde goruntulenebilir
- Sablonlar `wp_options` tablosunda saklanir (`kolai_contract_distance_sales`, `kolai_contract_preliminary_info`)
- Sablon bos birakilirsa varsayilan Turkce sablon kullanilir
