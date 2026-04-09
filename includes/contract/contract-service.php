<?php
/**
 * Contract Service - Manages legal contract templates
 *
 * @package    Kolai
 * @subpackage Kolai/includes/contract
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract Service class
 */
class Kolai_Contract_Service {

    /**
     * Available contract types.
     *
     * @return array
     */
    public function get_available_types() {
        return array(
            'distance_sales'   => 'Mesafeli Satis Sozlesmesi',
            'preliminary_info' => 'On Bilgilendirme Formu',
        );
    }

    /**
     * Get placeholder definitions for admin UI reference.
     *
     * @return array
     */
    public function get_placeholder_definitions() {
        return array(
            '{{buyer_name}}'                 => 'Alici adi',
            '{{buyer_email}}'                => 'Alici e-posta adresi',
            '{{buyer_phone}}'                => 'Alici telefonu',
            '{{buyer_address}}'              => 'Alici adresi',
            '{{order_date}}'                 => 'Siparis tarihi',
            '{{order_total}}'                => 'Siparis toplami',
            '{{order_currency}}'             => 'Para birimi',
            '{{payment_method}}'             => 'Odeme yontemi',
            '{{shipping_method}}'            => 'Kargo yontemi',
            '{{shipping_cost}}'              => 'Kargo ucreti',
            '{{product_list}}'               => 'Urun listesi (HTML tablo)',
        );
    }

    /**
     * Get seller and contract settings stored in admin.
     *
     * @return array
     */
    public function get_admin_replacements() {
        return array(
            '{{seller_name}}'                => get_option('kolai_seller_name', ''),
            '{{seller_address}}'             => get_option('kolai_seller_address', ''),
            '{{seller_phone}}'               => get_option('kolai_seller_phone', ''),
            '{{seller_email}}'               => get_option('kolai_seller_email', ''),
            '{{seller_tax_id}}'              => get_option('kolai_seller_tax_id', ''),
            '{{seller_mersis_no}}'           => get_option('kolai_seller_mersis_no', ''),
            '{{delivery_date}}'              => get_option('kolai_delivery_date', ''),
            '{{right_of_withdrawal_period}}' => get_option('kolai_right_of_withdrawal_period', ''),
        );
    }

    /**
     * Get contract template with all placeholders preserved for the client.
     *
     * @param string $type Contract type key.
     * @return array
     * @throws Kolai_Invalid_Contract_Type_Exception
     * @throws Kolai_Contract_Not_Found_Exception
     */
    public function get_contract($type) {
        $template = $this->get_template($type);

        if (empty($template)) {
            throw new Kolai_Contract_Not_Found_Exception("Contract template not found for type: {$type}");
        }

        $content = strtr($template, $this->get_admin_replacements());

        return array(
            'content'      => $content,
            'placeholders' => $this->get_placeholders_for_content($content),
        );
    }

    /**
     * Get all contract templates keyed by contract type.
     *
     * @return array
     * @throws Kolai_Contract_Not_Found_Exception
     * @throws Kolai_Invalid_Contract_Type_Exception
     */
    public function get_contracts() {
        $contracts = array();

        foreach ($this->get_available_types() as $type => $title) {
            $contract = $this->get_contract($type);
            $contracts[$type] = array(
                'title'        => $title,
                'content'      => $contract['content'],
                'placeholders' => $contract['placeholders'],
            );
        }

        $clarification_text = null;
        try {
            $clarification_text = $this->get_clarification_text_link();
        } catch (Kolai_Not_Found_Exception $e) {
            // Page not configured — leave as null
        }
        $contracts['clarificationText'] = $clarification_text;

        return $contracts;
    }

    /**
     * Get the selected clarification text page link.
     *
     * @return array
     * @throws Kolai_Not_Found_Exception
     */
    public function get_clarification_text_link() {
        $page_id = absint(get_option('kolai_clarification_text_page_id', 0));

        if (!$page_id) {
            throw new Kolai_Not_Found_Exception('Clarification text page is not configured');
        }

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
            throw new Kolai_Not_Found_Exception('Clarification text page not found');
        }

        $url = get_permalink($page_id);
        if (!$url) {
            throw new Kolai_Not_Found_Exception('Clarification text page URL not found');
        }

        return array(
            'pageId' => $page_id,
            'title'  => get_the_title($page_id),
            'url'    => $url,
        );
    }

    /**
     * Get raw template from wp_options.
     *
     * @param string $type Contract type key.
     * @return string
     * @throws Kolai_Invalid_Contract_Type_Exception
     */
    public function get_template($type) {
        $types = $this->get_available_types();

        if (!isset($types[$type])) {
            throw new Kolai_Invalid_Contract_Type_Exception("Invalid contract type: {$type}");
        }

        $option_key = 'kolai_contract_' . $type;
        $template = get_option($option_key, '');

        if (empty($template)) {
            $template = $this->get_default_template($type);
        }

        return $template;
    }

    /**
     * Save a template to wp_options.
     *
     * @param string $type    Contract type key.
     * @param string $content HTML template content.
     * @throws Kolai_Invalid_Contract_Type_Exception
     */
    public function save_template($type, $content) {
        $types = $this->get_available_types();

        if (!isset($types[$type])) {
            throw new Kolai_Invalid_Contract_Type_Exception("Invalid contract type: {$type}");
        }

        $option_key = 'kolai_contract_' . $type;
        update_option($option_key, $content);
    }

    /**
     * Collect placeholders present in content.
     *
     * @param string $content
     * @return array
     */
    private function get_placeholders_for_content($content) {
        $available_placeholders = $this->get_placeholder_definitions();
        $placeholders = array();

        foreach ($available_placeholders as $key => $description) {
            if (strpos($content, $key) !== false) {
                $placeholders[$key] = $description;
            }
        }

        return $placeholders;
    }

    /**
     * Get default template for a contract type.
     *
     * @param string $type
     * @return string
     */
    private function get_default_template($type) {
        if ($type === 'distance_sales') {
            return $this->get_default_distance_sales_template();
        }

        if ($type === 'preliminary_info') {
            return $this->get_default_preliminary_info_template();
        }

        return '';
    }

    /**
     * Default Mesafeli Satis Sozlesmesi template.
     *
     * @return string
     */
    private function get_default_distance_sales_template() {
        return '<h1>Mesafeli Satis Sozlesmesi</h1>

<h2>MADDE 1 - TARAFLAR</h2>

<h3>1.1 SATICI</h3>
<p>
<strong>Unvan:</strong> {{seller_name}}<br>
<strong>Adres:</strong> {{seller_address}}<br>
<strong>Telefon:</strong> {{seller_phone}}<br>
<strong>E-posta:</strong> {{seller_email}}<br>
<strong>VKN:</strong> {{seller_tax_id}}<br>
<strong>MERSIS No:</strong> {{seller_mersis_no}}
</p>

<h3>1.2 ALICI</h3>
<p>
<strong>Ad Soyad:</strong> {{buyer_name}}<br>
<strong>Adres:</strong> {{buyer_address}}<br>
<strong>Telefon:</strong> {{buyer_phone}}<br>
<strong>E-posta:</strong> {{buyer_email}}
</p>

<h2>MADDE 2 - SOZLESME KONUSU</h2>
<p>Isbu sozlesmenin konusu, ALICI\'nin SATICI\'ya ait internet sitesinden elektronik ortamda siparisini verdigi asagida nitelikleri ve satis fiyati belirtilen urunun satisi ve teslimi ile ilgili olarak 6502 sayili Tuketicinin Korunmasi Hakkinda Kanun ve Mesafeli Sozlesmeler Yonetmeligi hukumleri geregi taraflarin hak ve yukumluluklerinin belirlenmesidir.</p>

<h2>MADDE 3 - SOZLESME KONUSU URUN BILGILERI</h2>
{{product_list}}
<p>
<strong>Siparis Tarihi:</strong> {{order_date}}<br>
<strong>Toplam Tutar:</strong> {{order_total}} {{order_currency}}<br>
<strong>Kargo Ucreti:</strong> {{shipping_cost}} {{order_currency}}<br>
<strong>Odeme Yontemi:</strong> {{payment_method}}<br>
<strong>Teslimat Sekli:</strong> {{shipping_method}}<br>
<strong>Tahmini Teslim Tarihi:</strong> {{delivery_date}}
</p>

<h2>MADDE 4 - GENEL HUKUMLER</h2>
<p>4.1 ALICI, sozlesme konusu urunun temel nitelikleri, satis fiyati, odeme sekli ve teslimata iliskin tum on bilgileri okuyup bilgi sahibi oldugunu ve elektronik ortamda gerekli teyidi verdiklerini kabul ve beyan eder.</p>
<p>4.2 Sozlesme konusu urun, yasal 30 gunluk sure icinde ALICI veya gosterdigi adresteki kisi/kurulusa teslim edilir. Urunun SATICI tarafindan kargoya verilmesinden sonra ve ALICI tarafindan teslim alinmasinin ardindan, urune iliskin hasar veya kayiptan kargo firmasi sorumludur.</p>
<p>4.3 Sozlesme konusu urun, ALICI\'dan baska bir kisi/kurulusa teslim edilecek ise, teslim edilecek kisi/kurulusun teslimat kabul etmemesinden SATICI sorumlu tutulamaz.</p>

<h2>MADDE 5 - CAYMA HAKKI</h2>
<p>ALICI, sozlesme konusu urunun kendisine veya gosterdigi adresteki kisi/kurulusa tesliminden itibaren {{right_of_withdrawal_period}} icinde cayma hakkina sahiptir. Cayma hakki suresi sona ermeden once, SATICI\'nin onayi ile urunun kullanilmamis olmasi sartiyla cayma hakkinin kullanilmasi mumkundur.</p>

<h2>MADDE 6 - YETKI</h2>
<p>Isbu sozlesmeden dogan uyusmazliklarda Tuketici Hakem Heyetleri ve Tuketici Mahkemeleri yetkilidir.</p>

<p>Isbu sozlesme elektronik ortamda taraflarca okunarak kabul edilip teyit edilmistir. {{order_date}}</p>';
    }

    /**
     * Default On Bilgilendirme Formu template.
     *
     * @return string
     */
    private function get_default_preliminary_info_template() {
        return '<h1>On Bilgilendirme Formu</h1>

<p>6502 sayili Tuketicinin Korunmasi Hakkinda Kanun ve Mesafeli Sozlesmeler Yonetmeligi uyarinca, asagidaki bilgiler tuketiciye on bilgilendirme amaciyla sunulmaktadir.</p>

<h2>1. SATICI BILGILERI</h2>
<p>
<strong>Unvan:</strong> {{seller_name}}<br>
<strong>Adres:</strong> {{seller_address}}<br>
<strong>Telefon:</strong> {{seller_phone}}<br>
<strong>E-posta:</strong> {{seller_email}}<br>
<strong>VKN:</strong> {{seller_tax_id}}<br>
<strong>MERSIS No:</strong> {{seller_mersis_no}}
</p>

<h2>2. ALICI BILGILERI</h2>
<p>
<strong>Ad Soyad:</strong> {{buyer_name}}<br>
<strong>Adres:</strong> {{buyer_address}}<br>
<strong>Telefon:</strong> {{buyer_phone}}<br>
<strong>E-posta:</strong> {{buyer_email}}
</p>

<h2>3. SIPARIS BILGILERI</h2>
{{product_list}}
<p>
<strong>Siparis Tarihi:</strong> {{order_date}}<br>
<strong>Urun Toplami:</strong> {{order_total}} {{order_currency}}<br>
<strong>Kargo Ucreti:</strong> {{shipping_cost}} {{order_currency}}<br>
<strong>Odeme Yontemi:</strong> {{payment_method}}<br>
<strong>Teslimat Sekli:</strong> {{shipping_method}}<br>
<strong>Tahmini Teslim Tarihi:</strong> {{delivery_date}}
</p>

<h2>4. CAYMA HAKKI</h2>
<p>Tuketici, urunun teslim tarihinden itibaren {{right_of_withdrawal_period}} icinde herhangi bir gerekce gostermeksizin ve cezai sart odemeksizin sozlesmeden cayma hakkina sahiptir. Cayma hakkinin kullanilmasi icin bu sure icinde SATICI\'ya yazili bildirimde bulunulmasi ve urunun ilgili maddeler cercevesinde kullanilmamis olmasi gerekmektedir.</p>

<h2>5. GENEL BILGILER</h2>
<p>5.1 Sozlesme konusu urun, ALICI\'ya veya gosterdigi adresteki kisi/kurulusa yasal sureler icinde teslim edilir.</p>
<p>5.2 Urun ile ilgili tum vergiler dahil toplam fiyat yukarida belirtilmistir.</p>
<p>5.3 Odeme, belirtilen odeme yontemi ile yapilacaktir.</p>
<p>5.4 Urun teslimatina iliskin kargo ucreti ALICI tarafindan karsilanacaktir (aksi belirtilmedikce).</p>

<p>Bu on bilgilendirme formu, mesafeli satis sozlesmesinin ayrilmaz bir parcasidir. {{order_date}}</p>';
    }
}
