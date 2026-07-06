# Kolai API - Sozlesme (Contract) Endpoint'leri

Base URL: `https://your-site.com/wp-json/kolai/v1`

Genel response formati ve hata kodlari icin [README.md](README.md) dosyasina bakin.

## Hata Kodlari (Contract)

- `1002` Not found
- `5002` Contract not found

---

## POST /contracts

Tum sozlesme sablonlarini tek request ile dondurur. `distance_sales` ve `preliminary_info` icerikleri birlikte gelir.

Satici bilgileri (`{{seller_*}}`), tahmini teslim tarihi (`{{delivery_date}}`), cayma hakki suresi (`{{right_of_withdrawal_period}}`) admin panelinden girilen degerlerle otomatik doldurulur. Kalan placeholder'lar istemci (mobil) tarafinda doldurulmalidir. Response ayrica `clarificationText` alanini icerir.

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
      "content": "<h1>Mesafeli Satis Sozlesmesi</h1><h2>MADDE 1 - TARAFLAR</h2><h3>1.1 SATICI</h3><p><strong>Unvan:</strong> Ornek Ticaret A.S.<br>...",
      "placeholders": {
        "{{buyer_name}}": "Alici adi",
        "{{buyer_company_name}}": "Alici firma unvani",
        "{{buyer_tax_id}}": "Alici VKN / TCKN",
        "{{buyer_tax_office}}": "Alici vergi dairesi",
        "{{buyer_email}}": "Alici e-posta adresi",
        "{{buyer_phone}}": "Alici telefonu",
        "{{buyer_address}}": "Alici adresi",
        "{{order_date}}": "Siparis tarihi",
        "{{order_total}}": "Siparis toplami",
        "{{order_currency}}": "Para birimi",
        "{{payment_method}}": "Odeme yontemi",
        "{{shipping_method}}": "Kargo yontemi",
        "{{shipping_cost}}": "Kargo ucreti",
        "{{product_list}}": "Urun listesi (HTML tablo)"
      }
    },
    "preliminary_info": {
      "title": "On Bilgilendirme Formu",
      "content": "<h1>On Bilgilendirme Formu</h1><p>6502 sayili Tuketicinin Korunmasi Hakkinda Kanun ...",
      "placeholders": {
        "{{buyer_name}}": "Alici adi",
        "{{buyer_company_name}}": "Alici firma unvani",
        "{{buyer_tax_id}}": "Alici VKN / TCKN",
        "{{buyer_tax_office}}": "Alici vergi dairesi",
        "{{buyer_email}}": "Alici e-posta adresi",
        "{{buyer_phone}}": "Alici telefonu",
        "{{buyer_address}}": "Alici adresi",
        "{{order_date}}": "Siparis tarihi",
        "{{order_total}}": "Siparis toplami",
        "{{order_currency}}": "Para birimi",
        "{{payment_method}}": "Odeme yontemi",
        "{{shipping_method}}": "Kargo yontemi",
        "{{shipping_cost}}": "Kargo ucreti",
        "{{product_list}}": "Urun listesi (HTML tablo)"
      }
    },
    "clarificationText": {
      "pageId": 42,
      "title": "Aydinlatma Metni",
      "url": "https://your-site.com/aydinlatma-metni/"
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

Sablonlarda `{{placeholder}}` formati kullanilir.

### Admin Panelinden Doldurulan Alanlar

Asagidaki alanlar WP Admin > Kolai > Sozlesmeler sayfasindan girilir ve API yanitinda otomatik olarak doldurulur:

| Yer Tutucu | Aciklama |
|------------|----------|
| `{{seller_name}}` | Satici adi |
| `{{seller_address}}` | Satici adresi |
| `{{seller_phone}}` | Satici telefonu |
| `{{seller_email}}` | Satici e-posta adresi |
| `{{seller_tax_id}}` | Satici VKN |
| `{{seller_mersis_no}}` | Satici MERSIS numarasi |
| `{{delivery_date}}` | Tahmini teslim tarihi |
| `{{right_of_withdrawal_period}}` | Cayma hakki suresi |

### Istemci Tarafinda Doldurulan Alanlar

Asagidaki alanlar API yanitinda placeholder olarak korunur ve istemci tarafinda doldurulmalidir:

| Yer Tutucu | Aciklama |
|------------|----------|
| `{{buyer_name}}` | Alici adi |
| `{{buyer_company_name}}` | Alici firma unvani |
| `{{buyer_tax_id}}` | Alici VKN / TCKN |
| `{{buyer_tax_office}}` | Alici vergi dairesi |
| `{{buyer_email}}` | Alici e-posta adresi |
| `{{buyer_phone}}` | Alici telefonu |
| `{{buyer_address}}` | Alici adresi |
| `{{order_date}}` | Siparis tarihi |
| `{{order_total}}` | Siparis toplami |
| `{{order_currency}}` | Para birimi |
| `{{payment_method}}` | Odeme yontemi |
| `{{shipping_method}}` | Kargo yontemi |
| `{{shipping_cost}}` | Kargo ucreti |
| `{{product_list}}` | Urun listesi (HTML tablo olarak istemci olusturur) |

---

## Yonetici Paneli

WP Admin > Kolai > Ayarlar sayfasindan:

- Var olan WordPress sayfalari arasindan bir Aydinlatma Metni sayfasi secilebilir

WP Admin > Kolai > Sozlesmeler sayfasindan:

- Satici bilgileri (ad, adres, telefon, e-posta, VKN, MERSIS) girilebilir; API bu alanlari sablonlarda otomatik doldurur
- Tahmini teslim tarihi ve cayma hakki suresi girilebilir; API bu alanlari sablonlarda otomatik doldurur
- Her iki sozlesme sablonu `wp_editor` ile duzenlenebilir
- Yer tutucu referans paneli acilir/kapanir sekilde goruntulenebilir
- Sablonlar ve ayarlar `wp_options` tablosunda saklanir (`kolai_contract_distance_sales`, `kolai_contract_preliminary_info`, `kolai_seller_name`, `kolai_seller_address`, `kolai_seller_phone`, `kolai_seller_email`, `kolai_seller_tax_id`, `kolai_seller_mersis_no`, `kolai_delivery_date`, `kolai_right_of_withdrawal_period`, `kolai_clarification_text_page_id`)
- Sablon bos birakilirsa varsayilan Turkce sablon kullanilir
