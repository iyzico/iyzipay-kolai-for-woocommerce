# Kolai API - Sozlesme (Contract) Endpoint'leri

Base URL: `https://your-site.com/wp-json/kolai/v1`

Genel response formati ve hata kodlari icin [README.md](README.md) dosyasina bakin.

## Hata Kodlari (Contract)

- `1002` Not found
- `5002` Contract not found

---

## POST /contracts

Tum sozlesme sablonlarini tek request ile dondurur. `distance_sales` ve `preliminary_info` icerikleri birlikte gelir.

Tum placeholder'lar, `{{seller_*}}` alanlari dahil, oldugu gibi korunur. Sozlesme icerigindeki tum alanlari doldurmak istemci (mobil) tarafin sorumlulugundadir.

### Request

```
POST /wp-json/kolai/v1/contracts
```

Request body zorunlu degildir. Bos body veya bos JSON gonderilebilir:

```json
{}
```

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
    "distance_sales": {
      "title": "Mesafeli Satis Sozlesmesi",
      "content": "<h1>Mesafeli Satis Sozlesmesi</h1><h2>MADDE 1 - TARAFLAR</h2><h3>1.1 SATICI</h3><p><strong>Unvan:</strong> {{seller_name}}<br>...",
      "placeholders": {
        "{{seller_name}}": "Satici adi",
        "{{seller_address}}": "Satici adresi",
        "{{seller_phone}}": "Satici telefonu",
        "{{seller_email}}": "Satici e-posta adresi",
        "{{seller_tax_id}}": "Satici VKN",
        "{{seller_mersis_no}}": "Satici MERSIS numarasi",
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
    },
    "preliminary_info": {
      "title": "On Bilgilendirme Formu",
      "content": "<h1>On Bilgilendirme Formu</h1><p>6502 sayili Tuketicinin Korunmasi Hakkinda Kanun ...",
      "placeholders": {
        "{{seller_name}}": "Satici adi",
        "{{seller_address}}": "Satici adresi",
        "{{seller_phone}}": "Satici telefonu",
        "{{seller_email}}": "Satici e-posta adresi",
        "{{seller_tax_id}}": "Satici VKN",
        "{{seller_mersis_no}}": "Satici MERSIS numarasi",
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
}
```

### Response (template not found)

```json
{
  "status": "failure",
  "systemTime": "2026-03-25T12:00:00+00:00",
  "errorCode": "5002",
  "errorMessage": "Contract template not found for type: distance_sales",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

---

## GET /contracts/clarification-text

Ayarlar sayfasinda secilen Aydinlatma Metni sayfasinin linkini dondurur.

### Request

```
GET /wp-json/kolai/v1/contracts/clarification-text
```

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
    "pageId": 42,
    "title": "Aydinlatma Metni",
    "url": "https://your-site.com/aydinlatma-metni/"
  }
}
```

### Response (page not configured or not found)

```json
{
  "status": "failure",
  "systemTime": "2026-03-25T12:00:00+00:00",
  "errorCode": "1002",
  "errorMessage": "Clarification text page is not configured",
  "woocommerceVersion": "10.4.3",
  "wordpressVersion": "6.9.1",
  "phpVersion": "8.2.4",
  "data": null
}
```

---

## Yer Tutucular (Placeholders)

Sablonlarda `{{placeholder}}` formati kullanilir. Tum alanlar istemci tarafinda doldurulur.

### Satici Bilgileri

| Yer Tutucu | Aciklama |
|------------|----------|
| `{{seller_name}}` | Satici adi |
| `{{seller_address}}` | Satici adresi |
| `{{seller_phone}}` | Satici telefonu |
| `{{seller_email}}` | Satici e-posta adresi |
| `{{seller_tax_id}}` | Satici VKN |
| `{{seller_mersis_no}}` | Satici MERSIS numarasi |

### Alici / Siparis Bilgileri

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

WP Admin > Kolai > Ayarlar sayfasindan:

- Var olan WordPress sayfalari arasindan bir Aydinlatma Metni sayfasi secilebilir

WP Admin > Kolai > Sozlesmeler sayfasindan:

- Satici VKN ve MERSIS numarasi girilebilir; API bu alanlari otomatik replace etmez
- Her iki sozlesme sablonu `wp_editor` ile duzenlenebilir
- Yer tutucu referans paneli acilir/kapanir sekilde goruntulenebilir
- Sablonlar `wp_options` tablosunda saklanir (`kolai_contract_distance_sales`, `kolai_contract_preliminary_info`, `kolai_clarification_text_page_id`)
- Sablon bos birakilirsa varsayilan Turkce sablon kullanilir
