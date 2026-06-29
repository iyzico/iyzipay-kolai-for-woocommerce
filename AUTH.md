# KolAI API Kimlik Dogrulama

## Genel Bakis

Tum KolAI REST API endpointleri HMAC-SHA256 imza dogrulamasi ile korunmaktadir. Java sistemi (iyzipay shoplist) her istegi `IYZ-TP-v2` yetkilendirme semasi ile imzalar ve WordPress eklentisi istegi islemeden once imzayi dogrular.

## Yapilandirma

`wp_options` tablosundaki iki ayar (yonetici ayarlar sayfasindan yapilandirilir):

| Option key         | Gorevi                                          |
|--------------------|--------------------------------------------------|
| `kolai_api_key`    | Client ID — arayan sistemi tanimlar              |
| `kolai_secret_key` | Paylasilan gizli anahtar — HMAC hesaplamada kullanilir |

## Header Formati

```
Authorization: IYZ-TP-v2 {base64_payload}
```

Base64 payload cozuldugunde:

```
clientId:{deger}&salt:{deger}&scope:{deger}&timestamp:{deger}&signature:{deger}
```

| Alan        | Aciklama                                                  |
|-------------|-----------------------------------------------------------|
| `clientId`  | wp_options'taki `kolai_api_key` ile eslesmelidir          |
| `salt`      | Her istek icin uretilen rastgele UUID                     |
| `scope`     | Endpoint'e ozel enum degeri (asagidaki tabloya bakiniz)   |
| `timestamp` | (Opsiyonel, tavsiye edilir) Istegin uretildigi unix epoch saniyesi |
| `signature` | HMAC-SHA256 hex ozeti                                     |

## Imza Hesaplama

```
signature = HMAC-SHA256(secretKey, salt + scope + uriPath + requestBody + timestamp)
```

- **secretKey**: wp_options'taki `kolai_secret_key` degeri
- **salt**: Header'daki rastgele UUID
- **scope**: Header'daki scope dizesi
- **uriPath**: URL'nin path kismi (orn. `/wp-json/kolai/v1/products/123`), query string haric
- **requestBody**: POST/PATCH icin ham JSON body; GET icin bos string
- **timestamp**: Gonderildiginde imza mesajinin **sonuna** eklenir (unix epoch saniye). Gonderilmediginde imza mesajina hicbir sey eklenmez (geriye donuk uyumlu).

Sonuc hex olarak kodlanir (kucuk harf).

## Tekrar Saldirisi (Replay) Korumasi

- **Tek kullanimlik salt**: Sunucu, dogrulanan her istegin `salt` degerini kisa bir sure (varsayilan 600 sn) hatirlar. Ayni imzali istek aynen tekrar gonderilirse (ayni salt) `Replayed request rejected` hatasi ile reddedilir. Bu koruma **istemci tarafinda degisiklik gerektirmez** — her istek zaten benzersiz bir salt uretir.
- **Zaman damgasi (timestamp)**: Istemci imzali bir `timestamp` gonderdiginde, sunucu istegi izin verilen pencerenin (varsayilan ±300 sn) disindaysa `Request timestamp outside allowed window` ile reddeder. Bu, salt hatirlama suresinin otesindeki tekrarlari da engeller.
- **Gecis**: `timestamp` gondermeyen istemciler calismaya devam eder ancak sunucu bir deprecation uyarisi loglar. Java imzalayicisi guncellenip her istege `timestamp` ekledikten sonra tam koruma devreye girer.
- **Gizli anahtar zorunlulugu**: `kolai_api_key` veya `kolai_secret_key` bos ise tum istekler `Server credentials not configured` ile reddedilir (fail-closed).

## Scope Eslemesi

| Endpoint                            | Metod  | Scope                           |
|-------------------------------------|--------|---------------------------------|
| `/wp-json/kolai/v1/products`        | GET    | `RETRIEVE_PRODUCTS`             |
| `/wp-json/kolai/v1/products/{id}`   | GET    | `RETRIEVE_PRODUCT`              |
| `/wp-json/kolai/v1/products-with-variants/{id}` | GET | `RETRIEVE_PRODUCT_WITH_VARIANTS` |
| `/wp-json/kolai/v1/shipment-options`| POST   | `RETRIEVE_SHIPMENT_OPTIONS`     |
| `/wp-json/kolai/v1/orders`          | POST   | `CREATE_ORDER`                  |
| `/wp-json/kolai/v1/order-types`     | GET    | `RETRIEVE_ORDER_TYPES`          |
| `/wp-json/kolai/v1/orders/{id}`     | GET    | `RETRIEVE_ORDER`                |
| `/wp-json/kolai/v1/orders/{id}`     | PATCH  | `UPDATE_ORDER_STATUS`           |
| `/wp-json/kolai/v1/contracts`       | POST   | `RETRIEVE_CONTRACT`             |
| `/wp-json/kolai/v1/contracts/clarification-text` | GET | `RETRIEVE_CONTRACT` |
| `/wp-json/kolai/v1/products/{id}/reviews` | GET | `RETRIEVE_REVIEWS`        |
| `/wp-json/kolai/v1/reviews/{id}`    | GET    | `RETRIEVE_REVIEW`               |

## Hata Yaniti

Kimlik dogrulama basarisiz oldugunda, API standart Kolai yanit zarfi ile HTTP 401 dondurur:

```json
{
  "status": "failure",
  "systemTime": "2026-03-25T12:00:00+00:00",
  "errorCode": "1005",
  "errorMessage": "Unauthorized",
  "woocommerceVersion": "9.x.x",
  "wordpressVersion": "6.x",
  "phpVersion": "8.x.x",
  "data": null
}
```

## Uygulama Detaylari

- **Zamanlama-guvenli karsilastirma**: `hash_equals()` imza dogrulamasinda zamanlama saldirilarina karsi koruma saglar
- **URI path kaynagi**: Java'nin `URI.getPath()` davranisini eslestirmek icin `$_SERVER['REQUEST_URI']` kullanilir (query string cikarilir) — bu, `$request->get_route()` metodunun atladigi `/wp-json` onekini icerir
- **Body isleme**: `$request->get_body()` ham body'yi dondurur; GET isteklerinde bos string donmesi Java'nin davranisi ile uyumludur
- **Hata formatlama**: `Kolai_API`'deki `rest_request_after_callbacks` filtresi WordPress'in varsayilan WP_Error formatini yakalar ve standart Kolai yanit zarfina sarar
