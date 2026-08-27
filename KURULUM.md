# iyzico Kolai for WooCommerce — Kurulum ve Canlıya Alma Rehberi

Bu rehber, WooCommerce mağazanızı iyzico Kolai platformuna bağlamak için baştan
sona yapılması gerekenleri anlatır. Sonundaki [Canlıya Alma Checklist](#canliya-alma-checklist)
tek tek işaretlenerek kullanılmak üzere hazırlanmıştır.

---

## 0. Ön Koşullar

| Gereksinim | Minimum | Not |
|---|---|---|
| WordPress | 5.0+ (6.9 ile test edildi) | |
| WooCommerce | 5.0+ (10.9.1 ile test edildi) | **Aktif** olmalı; değilse eklenti kendini devre dışı bırakır |
| PHP | 7.2+ | 8.1+ önerilir |
| HTTPS | Zorunlu | Kolai yalnızca `https://` uçlara istek atar |
| Kalıcı bağlantılar (Permalink) | "Sade" **olmamalı** | Aşağıdan kontrol edebilirsiniz |

### Permalink ayarı (atlanırsa imza doğrulaması patlar)

**Ayarlar → Kalıcı Bağlantılar** ekranında "Sade / Plain" dışında bir seçenek
(örn. "Yazı adı") seçili olmalıdır. Kolai imzası isteğin URL **path**'i üzerinden
hesaplanır (`/wp-json/kolai/v1/products`). Sade permalink'te REST çağrısı
`?rest_route=...` biçiminde gider, path `/index.php` olur ve **tüm istekler
`Invalid signature` ile 401 döner.**

### Google Merchant XML

iyzico Merchant Panel'de **Google Merchant XML'inizin girilmiş ve onaylanmış**
olması gerekir. XML üretimi ve gereksinimleri için:
<https://support.google.com/merchants/answer/14987622?hl=tr>

Bu adım tamamlanmadan Kolai ürün eşleştirmesi başlatılamaz.

---

## 1. Eklentiyi Kurun

**Yöntem A — WP Admin üzerinden (önerilen)**

1. `iyzipay-kolai-for-woocommerce` klasörünü ZIP'leyin.
2. **Eklentiler → Yeni Ekle → Eklenti Yükle** → ZIP'i seçin → **Şimdi Kur**.
3. **Etkinleştir**.

Ardından **Eklentiler** ekranından etkinleştirin.

> Etkinleştirme sırasında WooCommerce aktif değilse eklenti kendini kapatır ve
> "Kolai plugin'i WooCommerce gerektirir" uyarısı verir.

**Doğrulama:** Sol menüde **Kolai** görünmeli, altında `Sozlesmeler`,
`Meta Eslesme`, `Loglar` alt menüleri olmalı.

---

## 2. Kolai Platformunda Mağazayı Tanımlayın

iyzico Merchant Panel → Kolai ayarları:

| Alan | Değer |
|---|---|
| Altyapı sağlayıcısı | **WooCommerce** |
| Kolai API Base URL | `https://alanadiniz.com` |

Base URL'i girdikten sonra panelden **API Key** ve **Secret Key** üretin. Bu iki
değeri bir sonraki adımda WordPress'e **birebir** gireceksiniz (başında/sonunda
boşluk kalmasın).

---

## 3. WP Admin → Kolai → Ayarlar

### API Ayarları

| Alan | Açıklama |
|---|---|
| **API Key** | iyzico Merchant Panel içerisindeki Kolai API Key |
| **Secret Key** | iyzico Merchant Panel içerisindeki Kolai SECRET Key. Maskeli alandır: **boş bırakırsanız kayıtlı değer korunur**, silinmez |
| **Aydınlatma Metni Sayfası** | Yayınlanmış WordPress sayfalarından seçilir; `GET /contracts/clarification-text` bu sayfanın içeriğini döner |

> API Key veya Secret Key boşsa eklenti **fail-closed** çalışır: tüm Kolai
> istekleri `Server credentials not configured` ile 401 döner.

### iyzico İade / İptal Ayarları

WooCommerce paneli üzerinden iade/iptal yapılacaksa doldurulmalıdır.

| Alan | Açıklama |
|---|---|
| **iyzico API Key** | iyzico Merchant Panel API anahtarı |
| **iyzico Secret Key** | Maskeli alan; boş bırakılırsa mevcut değer korunur |
| **iyzico Ortam** | Test için `Sandbox`, canlı için **`Production`** |

Bu bilgiler girilmezse sipariş oluşturma çalışır, ancak WooCommerce'ten yapılan
iade/iptal iyzico'ya iletilmez.

### Kargo Yöntemleri (iyzico)

WooCommerce kargo bölgelerinizdeki aktif yöntemler listelenir. İşaretini
kaldırdığınız yöntem **yalnızca** `/shipment-options` ucundan gizlenir; normal
WooCommerce ödeme akışı etkilenmez. Varsayılan: hepsi açık.

**Ayarları Kaydet** deyin.

---

## 4. WP Admin → Kolai → Sözleşmeler

Bu ekrandaki değerler sözleşme şablonlarındaki yer tutucuları otomatik doldurur.

**Satıcı Bilgileri** — hepsi zorunlu:
- Satıcı Adı / Unvanı
- Satıcı Adresi
- Satıcı Telefonu
- Satıcı E-posta Adresi
- **Vergi Kimlik Numarası (VKN)**
- **MERSİS Numarası**

**Teslimat ve Cayma Hakkı:**
- Tahmini Teslim Tarihi (örn. "3 iş günü")
- Cayma Hakkı Süresi (örn. "14 gün")

**Sözleşme metinleri:** Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu
varsayılan metinlerle gelir. Hukuk biriminizle kontrol edip gerekirse düzenleyin;
düzenlemiyorsanız varsayılanları kabul edip kaydetmeniz yeterlidir.

**Sözleşmeleri Kaydet** deyin.

---

## 5. (Opsiyonel) Meta Alan Eşleşme

**Kolai → Meta Eşleşme** ekranı, siparişe yazılan meta anahtar adlarını
özelleştirmenizi sağlar (fatura tipi, TCKN/VKN, vergi dairesi, iyzico Payment ID,
taksit sayısı, vade farkı vb.). Fatura/muhasebe eklentileriniz farklı anahtar
adları bekliyorsa burada eşleyin.

Ayrıca `personal` / `company` fatura tipi değerlerini kendi eklentinizin beklediği
değerlere çevirebilirsiniz.

> Anahtar adını değiştirmek yalnızca **bundan sonraki** siparişleri etkiler.

---

## 6. Kurulumu Doğrulayın

### 6.1 REST route ayakta mı?

Terminalden (veya tarayıcıdan) imzasız bir istek atın:

```bash
curl -i https://alanadiniz.com/wp-json/kolai/v1/products
```

**Beklenen:** HTTP `401` ve şu gövde:

```json
{
  "status": "failure",
  "errorCode": "1005",
  "errorMessage": "Missing or invalid authorization header",
  ...
}
```

Bu yanıt "her şey doğru" demektir: route kayıtlı, permalink doğru, kimlik
doğrulama devrede.

| Aldığınız yanıt | Anlamı |
|---|---|
| `401` + `1005` | ✅ Doğru |
| `404` | Permalink sade kalmış ya da eklenti aktif değil |
| `200` + veri | ⛔ Kimlik doğrulama devre dışı — derhal iyzico'ya bildirin |
| `1004` | WooCommerce aktif değil |

### 6.2 Uçtan uca test

Kolai platformundan test isteği tetikleyip **Kolai → Loglar** ekranından takip edin:

1. **Loglar** ekranında "Log Tutmayı Etkinleştir" kutusunu işaretleyin, seviye
   `debug`, saklama süresi 7 gün → kaydedin.
2. Kolai tarafından ürün listeleme / kargo seçenekleri / test siparişi tetikleyin.
3. Log tablosunda `auth` ve istek kayıtlarını kontrol edin (kişisel veriler
   maskelenir).
4. Devreye alma bittikten sonra **logları kapatın** — sürekli açık kalması
   gerekmez.

Kontrol edilecekler:
- Ürünler doğru fiyat ve KDV dökümüyle geliyor mu?
- Kargo seçenekleri doğru bölge/ücretle dönüyor mu?
- Test siparişi WooCommerce'te oluşuyor, adres (il/ilçe) doğru doluyor mu?
- Sipariş toplamı iyzico `paidPrice` ile birebir eşleşiyor mu (taksitli işlemde
  "Vade Farkı" satırı)?
- WooCommerce'ten kısmi iade yapıldığında iyzico'ya iletiliyor mu?

---

<a id="canliya-alma-checklist"></a>
## 7. Canlıya Alma Checklist

### A. iyzico Merchant Panel

- [ ] XML alanında **production** XML aktif
- [ ] Google Merchant XML girilmiş ve **onaylanmış**
      ([kılavuz](https://support.google.com/merchants/answer/14987622?hl=tr))
- [ ] **Kolai ile Ürün Satışı** özelliği aktif
- [ ] **Kolai API Base URL** tanımlı (`https://alanadiniz.com`)
- [ ] **Altyapı sağlayıcısı** seçimi tanımlı (WooCommerce)
- [ ] **Kolai API Key** tanımlı
- [ ] **Kolai Secret Key** tanımlı

### B. WordPress / WooCommerce

- [ ] WooCommerce kurulu ve **aktif**
- [ ] Site HTTPS üzerinden yayında
- [ ] Kalıcı bağlantılar "Sade" değil
- [ ] Eklenti etkinleştirildi, sol menüde **"Kolai"** göründü

### C. Kolai → Ayarlar

- [ ] **API Key** girildi (platformdaki değerle birebir)
- [ ] **Secret Key** girildi (platformdaki değerle birebir)
- [ ] **Aydınlatma Metni** sayfası seçildi (sayfa yayında)
- [ ] **iyzico API Key** girildi
- [ ] **iyzico Secret Key** girildi
- [ ] **iyzico Ortam** = `Production` (canlı için)
- [ ] Kargo yöntemleri gözden geçirildi

> iyzico anahtarları yalnızca WooCommerce paneli üzerinden **iade/iptal**
> yapılacaksa zorunludur; yapılacaksa mutlaka doldurun.

### D. Kolai → Sözleşmeler

- [ ] Satıcı adı / unvanı, adresi, telefonu, e-postası girildi
- [ ] **VKN** girildi
- [ ] **MERSİS numarası** girildi
- [ ] **Tahmini teslim tarihi** girildi
- [ ] **Cayma hakkı süresi** girildi
- [ ] Mesafeli satış sözleşmesi metni kontrol edildi (veya varsayılan kabul edildi)
- [ ] Ön bilgilendirme formu metni kontrol edildi (veya varsayılan kabul edildi)

### E. Doğrulama

- [ ] `curl https://alanadiniz.com/wp-json/kolai/v1/products` → `401` + `1005`
- [ ] Kolai'den ürün listeleme başarılı
- [ ] Kargo seçenekleri doğru dönüyor
- [ ] Test siparişi WooCommerce'te oluştu, adres ve toplam doğru
- [ ] Test iadesi iyzico'ya iletildi
- [ ] Devreye alma sonrası loglar kapatıldı

---

## 8. Canlıya Alma Talebi

Yukarıdaki tüm maddeler tamamlandığında **entegrasyon@iyzico.com** adresine
canlıya alınabilir bilgisini gönderin.

**Örnek e-posta:**

> **Konu:** Kolai canlıya alma talebi — [Mağaza Adı] / [Merchant ID]
>
> Merhaba,
>
> Kolai WooCommerce entegrasyonu için gerekli tüm adımları tamamladık:
>
> - Merchant Panel: production XML aktif, Google Merchant XML onaylı, Kolai ile
>   Ürün Satışı aktif, Base URL + altyapı sağlayıcısı + API/Secret Key tanımlı.
> - WordPress: iyzico Kolai for WooCommerce v1.8.5 kurulu ve aktif; Kolai API
>   anahtarları, aydınlatma metni sayfası, iyzico iade/iptal anahtarları
>   (ortam: production) ve sözleşme bilgileri (VKN/MERSİS, teslimat ve cayma
>   hakkı süreleri dahil) girildi.
> - Doğrulama: `/wp-json/kolai/v1` uçları imza doğrulamasıyla yanıt veriyor; test
>   ürün listeleme, kargo seçenekleri, sipariş ve iade akışları başarılı.
>
> Mağaza URL: https://alanadiniz.com
> Merchant ID: ...
>
> Canlıya alınabilir. Teşekkürler.

---

## 9. Sorun Giderme

| Belirti | Olası neden | Çözüm |
|---|---|---|
| Tüm istekler `Invalid signature` | Permalink "Sade" | Kalıcı bağlantıları değiştirin |
| `Server credentials not configured` | API Key veya Secret Key boş | Kolai → Ayarlar'dan girin |
| `Invalid client credentials` | API Key platformdakiyle uyuşmuyor | Değeri birebir kopyalayın |
| `Replayed request rejected` | Aynı istek aynı `salt` ile tekrar gönderildi | Normal koruma; istemci her istekte yeni salt üretmeli |
| `Request timestamp outside allowed window` | Sunucu saati kaymış | NTP ile saati senkronlayın (±300 sn) |
| `1004 WooCommerce inactive` | WooCommerce devre dışı | WooCommerce'i etkinleştirin |
| Sipariş `city` alanı boş | Eski sürüm | v1.8.4+ sürümüne güncelleyin |
| Sipariş toplamı taksitte tutmuyor | Eski sürüm | v1.8.2+ sürümüne güncelleyin |
| Loglar boş | Log tutma kapalı | Kolai → Loglar → "Log Tutmayı Etkinleştir" |

Detaylı teknik dokümantasyon: [README.md](README.md) ve ilgili
[AUTH.md](AUTH.md) / [PRODUCT.md](PRODUCT.md) / [ORDER.md](ORDER.md) /
[SHIPPING.md](SHIPPING.md) / [CONTRACT.md](CONTRACT.md) / [REFUND.md](REFUND.md) /
[LOGS.md](LOGS.md) dosyaları.
