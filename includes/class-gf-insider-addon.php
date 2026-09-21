<?php
/**
 * Gravity Forms feed add-on: one feed per form says which entry values become
 * the Insider contact, and which event the submission fires through the upsert API.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

final class GF_Insider_Addon extends GFFeedAddOn {

	protected $_version                  = GF_INSIDER_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = GF_INSIDER_SLUG;
	protected $_path                     = GF_INSIDER_SLUG . '/gf-insider.php';
	protected $_full_path                = GF_INSIDER_FILE;
	protected $_title                    = 'Insider';
	protected $_short_title              = 'Insider';

	const UPSERT_URL = 'https://unification.useinsider.com/api/user/v1/upsert';

	const PROFILE_URL = 'https://unification.useinsider.com/api/user/v1/profile';

	/** @var GF_Insider_Addon|null */
	private static $instance = null;

	public static function get_instance(): GF_Insider_Addon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// ---------------------------------------------------------------- settings

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'Conta da Insider', 'gf-insider' ),
				'description' => esc_html__( 'Os dois valores aparecem na tag que a Insider fornece: https://{nome}.api.useinsider.com/ins.js?id={id}', 'gf-insider' ),
				'fields'      => array(
					array(
						'name'     => 'partner_name',
						'label'    => esc_html__( 'Nome do parceiro', 'gf-insider' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => esc_html__( 'O subdomínio da tag, a parte antes de .api.useinsider.com.', 'gf-insider' ),
					),
					array(
						'name'     => 'partner_id',
						'label'    => esc_html__( 'ID do parceiro', 'gf-insider' ),
						'type'     => 'text',
						'class'    => 'small',
						'required' => true,
						'tooltip'  => esc_html__( 'O número depois de ?id= na tag.', 'gf-insider' ),
					),
					array(
						'name'       => 'api_key',
						'label'      => esc_html__( 'Chave da API', 'gf-insider' ),
						'type'       => 'text',
						'input_type' => 'password',
						'class'      => 'medium',
						'required'   => true,
						'tooltip'    => esc_html__( 'Chave do tipo Upsert, gerada no painel da Insider em Integration Settings › API Keys. Sem ela nenhum envio sai.', 'gf-insider' ),
					),
					array(
						'name'          => 'print_tag',
						'label'         => esc_html__( 'Tag da Insider', 'gf-insider' ),
						'type'          => 'toggle',
						'default_value' => true,
						'tooltip'       => esc_html__( 'Publica a tag no <head> de todas as páginas. Desligue se a tag já for injetada por um gerenciador de tags.', 'gf-insider' ),
					),
				),
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Evento', 'gf-insider' ),
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Nome', 'gf-insider' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
					),
					array(
						'name'          => 'eventName',
						'label'         => esc_html__( 'Nome do evento na Insider', 'gf-insider' ),
						'type'          => 'text',
						'class'         => 'medium',
						'required'      => true,
						'default_value' => 'form_submit',
						'tooltip'       => esc_html__( 'Precisa ser idêntico ao evento cadastrado no painel da Insider.', 'gf-insider' ),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Identificação do contato', 'gf-insider' ),
				'description' => esc_html__( 'O CPF e o uuid vão em identifiers. Sem eles, o e-mail identifica; sem e-mail, o telefone. Sem nenhum, o envio é ignorado.', 'gf-insider' ),
				'fields'      => array(
					array(
						'name'      => 'contact',
						'label'     => '',
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'email',
								'label'      => esc_html__( 'E-mail', 'gf-insider' ),
								'field_type' => array( 'email', 'text', 'hidden' ),
							),
							array(
								'name'       => 'phone_number',
								'label'      => esc_html__( 'Telefone', 'gf-insider' ),
								'field_type' => array( 'phone', 'text', 'hidden' ),
							),
							array(
								'name'       => 'name',
								'label'      => esc_html__( 'Nome', 'gf-insider' ),
								'tooltip'    => esc_html__( 'Um campo de nome completo também preenche o sobrenome.', 'gf-insider' ),
								'field_type' => array( 'name', 'text', 'hidden' ),
							),
							array(
								'name'       => 'surname',
								'label'      => esc_html__( 'Sobrenome', 'gf-insider' ),
								'field_type' => array( 'name', 'text', 'hidden' ),
							),
							array(
								'name'       => 'uuid',
								'label'      => esc_html__( 'ID do usuário (uuid)', 'gf-insider' ),
								'tooltip'    => esc_html__( 'O identificador principal da Insider: o id que a pessoa já tem no seu sistema. Deixe vazio se o formulário não souber esse id.', 'gf-insider' ),
								'field_type' => array( 'text', 'number', 'email', 'hidden' ),
							),
						),
					),
					array(
						'name'        => 'customIdentifiers',
						'label'       => esc_html__( 'Outros identificadores', 'gf-insider' ),
						'type'        => 'generic_map',
						'tooltip'     => esc_html__( 'Identificador com nome próprio, como o CPF: vai em identifiers e une este contato ao que o back-end já enviou.', 'gf-insider' ),
						'key_field'   => array(
							'title'   => esc_html__( 'Nome do identificador', 'gf-insider' ),
							'choices' => array(),
						),
						'value_field' => array(
							'title'   => esc_html__( 'Campo do formulário', 'gf-insider' ),
							'choices' => self::attribute_choices(),
						),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Consentimento', 'gf-insider' ),
				'description' => esc_html__( 'Campo marcado vira verdadeiro. Deixe em branco o canal que o formulário não pergunta: a Insider trata ausência como não informado.', 'gf-insider' ),
				'fields'      => array(
					array(
						'name'      => 'optins',
						'label'     => '',
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'gdpr_optin',
								'label'      => esc_html__( 'Consentimento de dados', 'gf-insider' ),
								'field_type' => array( 'consent', 'checkbox', 'radio', 'select', 'hidden' ),
							),
							array(
								'name'       => 'email_optin',
								'label'      => esc_html__( 'Aceita e-mail', 'gf-insider' ),
								'field_type' => array( 'consent', 'checkbox', 'radio', 'select', 'hidden' ),
							),
							array(
								'name'       => 'sms_optin',
								'label'      => esc_html__( 'Aceita SMS', 'gf-insider' ),
								'field_type' => array( 'consent', 'checkbox', 'radio', 'select', 'hidden' ),
							),
							array(
								'name'       => 'whatsapp_optin',
								'label'      => esc_html__( 'Aceita WhatsApp', 'gf-insider' ),
								'field_type' => array( 'consent', 'checkbox', 'radio', 'select', 'hidden' ),
							),
						),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Parâmetros do evento', 'gf-insider' ),
				'description' => esc_html__( 'Os dados do formulário vão em event_params.custom do evento. O nome do parâmetro precisa existir no painel da Insider.', 'gf-insider' ),
				'fields'      => array(
					array(
						'name'      => 'eventParameters',
						'label'     => '',
						'type'      => 'generic_map',
						'key_field' => array(
							'title'   => esc_html__( 'Parâmetro', 'gf-insider' ),
							'choices' => array(),
						),
						'value_field' => array(
							'title'      => esc_html__( 'Campo do formulário', 'gf-insider' ),
							'choices'    => self::attribute_choices(),
							'merge_tags' => true,
						),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Condição', 'gf-insider' ),
				'fields' => array(
					array(
						'name'           => 'feedCondition',
						'label'          => esc_html__( 'Enviar quando', 'gf-insider' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enviar só quando a condição for atendida', 'gf-insider' ),
					),
				),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function feed_list_columns() {
		return array(
			'feedName'  => esc_html__( 'Nome', 'gf-insider' ),
			'eventName' => esc_html__( 'Evento', 'gf-insider' ),
		);
	}

	public function get_menu_icon() {
		return 'gform-icon--cog';
	}

	/**
	 * Quebra de página, bloco de HTML e afins aparecem no mapa do Gravity Forms
	 * e não carregam valor: listá-los só convida a um mapeamento que não envia.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function attribute_choices(): array {
		$form = self::get_instance()->get_current_form();

		if ( ! is_array( $form ) ) {
			return array();
		}

		return self::get_field_map_choices(
			(int) rgar( $form, 'id' ),
			null,
			array( 'html', 'page', 'section', 'captcha', 'fileupload' )
		);
	}

	// ------------------------------------------------------------- processing

	/**
	 * @param array<string, mixed> $feed
	 * @param array<string, mixed> $entry
	 * @param array<string, mixed> $form
	 *
	 * @return array<string, mixed>
	 */
	public function process_feed( $feed, $entry, $form ) {
		$partner_name = trim( (string) $this->get_plugin_setting( 'partner_name' ) );
		$api_key      = trim( (string) $this->get_plugin_setting( 'api_key' ) );

		if ( '' === $partner_name || '' === $api_key ) {
			$this->add_feed_error( esc_html__( 'Nome do parceiro ou chave da API não configurados.', 'gf-insider' ), $feed, $entry, $form );

			return $entry;
		}

		$event = GF_Insider_Payload::event(
			(string) rgars( $feed, 'meta/eventName' ),
			GF_Insider_Payload::timestamp( (string) rgar( $entry, 'date_created' ) ),
			$this->get_generic_map_fields( $feed, 'eventParameters', $form, $entry )
		);

		$user = GF_Insider_Payload::user(
			$this->mapped_values( $feed, 'contact', $form, $entry ),
			$this->mapped_values( $feed, 'optins', $form, $entry ),
			$this->get_generic_map_fields( $feed, 'customIdentifiers', $form, $entry ),
			array() === $event ? array() : array( $event )
		);

		if ( array() === $user ) {
			$this->log_debug( __METHOD__ . '(): no identifier in the entry, nothing sent.' );

			return $entry;
		}

		$response = $this->post( self::UPSERT_URL, array( 'users' => array( $user ) ) );

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || $code < 200 || $code > 299 ) {
			$message = is_wp_error( $response ) ? $response->get_error_message() : $code . ' ' . wp_remote_retrieve_body( $response );

			$this->add_feed_error( sprintf( esc_html__( 'O envio para a Insider falhou: %s', 'gf-insider' ), $message ), $feed, $entry, $form );

			return $entry;
		}

		$body = wp_remote_retrieve_body( $response );

		// Insider answers 200 to a rejected user, with the reason in the body.
		if ( (int) rgars( (array) json_decode( $body, true ), 'data/fail/count' ) > 0 ) {
			$this->add_feed_error( sprintf( esc_html__( 'A Insider recusou o envio: %s', 'gf-insider' ), $body ), $feed, $entry, $form );

			return $entry;
		}

		$this->log_debug( __METHOD__ . '(): Insider answered ' . $code . '.' );

		// The upsert answer carries no profile id; the entry screen looks it up later.
		gform_update_meta( (int) rgar( $entry, 'id' ), 'insider_identifiers', wp_json_encode( $user['identifiers'] ) );

		return $entry;
	}

	/**
	 * @param array<string, mixed> $body
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function post( string $url, array $body ) {
		add_action( 'http_api_curl', array( __CLASS__, 'force_ipv4' ), 10, 3 );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'    => 'application/json',
					'X-PARTNER-NAME'  => trim( (string) $this->get_plugin_setting( 'partner_name' ) ),
					'X-REQUEST-TOKEN' => trim( (string) $this->get_plugin_setting( 'api_key' ) ),
				),
				'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ),
			)
		);

		remove_action( 'http_api_curl', array( __CLASS__, 'force_ipv4' ), 10 );

		return $response;
	}

	// ------------------------------------------------------------ entry screen

	public function init_admin() {
		parent::init_admin();

		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'entry_meta_boxes' ), 10, 3 );
	}

	/**
	 * @param array<string, array<string, mixed>> $meta_boxes
	 * @param array<string, mixed>                $entry
	 * @param array<string, mixed>                $form
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function entry_meta_boxes( $meta_boxes, $entry, $form ) {
		if ( '' === (string) gform_get_meta( (int) rgar( $entry, 'id' ), 'insider_identifiers' ) ) {
			return $meta_boxes;
		}

		$meta_boxes['gf_insider'] = array(
			'title'    => esc_html__( 'Insider', 'gf-insider' ),
			'callback' => array( $this, 'render_profile_box' ),
			'context'  => 'side',
		);

		return $meta_boxes;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function render_profile_box( $args ): void {
		$insider_id = $this->insider_id( (int) rgars( $args, 'entry/id' ) );

		if ( '' === $insider_id ) {
			echo esc_html__( 'Perfil ainda não encontrado na Insider. Recarregue em alguns minutos.', 'gf-insider' );

			return;
		}

		$url = sprintf(
			'https://%s.inone.useinsider.com/user-profiles/%s',
			rawurlencode( trim( (string) $this->get_plugin_setting( 'partner_name' ) ) ),
			rawurlencode( $insider_id )
		);

		printf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Ver perfil na Insider One', 'gf-insider' )
		);
	}

	/**
	 * Resolved on first view and cached, so the submission never waits on it
	 * and a profile Insider has not indexed yet is retried on the next view.
	 */
	private function insider_id( int $entry_id ): string {
		$cached = (string) gform_get_meta( $entry_id, 'insider_id' );

		if ( '' !== $cached ) {
			return $cached;
		}

		$identifiers = json_decode( (string) gform_get_meta( $entry_id, 'insider_identifiers' ), true );

		if ( ! is_array( $identifiers ) ) {
			return '';
		}

		$response   = $this->post( self::PROFILE_URL, array( 'identifiers' => $identifiers, 'attributes' => array( 'email' ) ) );
		$insider_id = (string) rgars( (array) json_decode( wp_remote_retrieve_body( $response ), true ), 'attributes/iid' );

		if ( '' === $insider_id ) {
			$this->log_debug( __METHOD__ . '(): no profile for entry ' . $entry_id . ': ' . wp_remote_retrieve_body( $response ) );

			return '';
		}

		gform_update_meta( $entry_id, 'insider_id', $insider_id );

		return $insider_id;
	}

	/**
	 * Insider's API key allowlist takes IPv4 only, and cURL prefers IPv6 when the
	 * host has it: the request then leaves from an address the key rejects with 403.
	 *
	 * @param resource|\CurlHandle $handle
	 * @param array<string, mixed> $args
	 * @param string               $url
	 */
	public static function force_ipv4( $handle, $args, $url ): void {
		if ( 0 === strpos( $url, 'https://unification.useinsider.com/' ) ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}
	}

	/**
	 * @param array<string, mixed> $feed
	 * @param array<string, mixed> $form
	 * @param array<string, mixed> $entry
	 *
	 * @return array<string, mixed>
	 */
	private function mapped_values( array $feed, string $field_name, array $form, array $entry ): array {
		$values = array();

		foreach ( self::get_field_map_fields( $feed, $field_name ) as $key => $field_id ) {
			if ( '' === (string) $field_id ) {
				continue;
			}

			$values[ $key ] = $this->get_field_value( $form, $entry, $field_id );
		}

		return $values;
	}
}
