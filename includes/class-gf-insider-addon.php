<?php
/**
 * Gravity Forms feed add-on: one feed per form says which entry values become
 * the Insider contact, and which event the submission fires.
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

	/** @var GF_Insider_Addon|null */
	private static $instance = null;

	/**
	 * Pushes waiting to be printed, keyed by form id: a page can hold more than
	 * one form, and only the submitted one gets the script.
	 *
	 * @var array<int, list<array<string, mixed>>>
	 */
	private $queue = array();

	public static function get_instance(): GF_Insider_Addon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init_frontend() {
		parent::init_frontend();

		// Late on purpose: the theme builds the confirmation markup at 10.
		add_filter( 'gform_confirmation', array( $this, 'append_queue' ), 999, 2 );
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
				'description' => esc_html__( 'A Insider precisa de pelo menos um entre identificador único, e-mail e telefone. Sem nenhum deles o envio é ignorado.', 'gf-insider' ),
				'fields'      => array(
					array(
						'name'      => 'contact',
						'label'     => '',
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'  => 'email',
								'label' => esc_html__( 'E-mail', 'gf-insider' ),
							),
							array(
								'name'  => 'phone_number',
								'label' => esc_html__( 'Telefone', 'gf-insider' ),
							),
							array(
								'name'    => 'name',
								'label'   => esc_html__( 'Nome', 'gf-insider' ),
								'tooltip' => esc_html__( 'Um campo de nome completo também preenche o sobrenome.', 'gf-insider' ),
							),
							array(
								'name'  => 'surname',
								'label' => esc_html__( 'Sobrenome', 'gf-insider' ),
							),
							array(
								'name'    => 'uuid',
								'label'   => esc_html__( 'Identificador único', 'gf-insider' ),
								'tooltip' => esc_html__( 'O que identifica a pessoa na base da Insider, por exemplo o CPF.', 'gf-insider' ),
							),
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
								'name'  => 'gdpr_optin',
								'label' => esc_html__( 'Consentimento de dados', 'gf-insider' ),
							),
							array(
								'name'  => 'email_optin',
								'label' => esc_html__( 'Aceita e-mail', 'gf-insider' ),
							),
							array(
								'name'  => 'sms_optin',
								'label' => esc_html__( 'Aceita SMS', 'gf-insider' ),
							),
							array(
								'name'  => 'whatsapp_optin',
								'label' => esc_html__( 'Aceita WhatsApp', 'gf-insider' ),
							),
						),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Atributos do contato', 'gf-insider' ),
				'description' => esc_html__( 'Vão em custom no contato da Insider. A chave precisa existir no painel da Insider como atributo customizado.', 'gf-insider' ),
				'fields'      => array(
					array(
						'name'      => 'userAttributes',
						'label'     => '',
						'type'      => 'generic_map',
						'key_field' => array(
							'title'   => esc_html__( 'Atributo', 'gf-insider' ),
							'choices' => array(),
						),
						'value_field' => array(
							'title'      => esc_html__( 'Campo do formulário', 'gf-insider' ),
							'merge_tags' => true,
						),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Parâmetros do evento', 'gf-insider' ),
				'description' => esc_html__( 'Vão em event_parameters.custom do evento.', 'gf-insider' ),
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

	// ------------------------------------------------------------- processing

	/**
	 * @param array<string, mixed> $feed
	 * @param array<string, mixed> $entry
	 * @param array<string, mixed> $form
	 *
	 * @return array<string, mixed>
	 */
	public function process_feed( $feed, $entry, $form ) {
		$form_id = (int) rgar( $form, 'id' );

		$user = GF_Insider_Payload::user(
			$this->mapped_values( $feed, 'contact', $form, $entry ),
			$this->mapped_values( $feed, 'optins', $form, $entry ),
			$this->get_generic_map_fields( $feed, 'userAttributes', $form, $entry )
		);

		$event = GF_Insider_Payload::event(
			(string) rgars( $feed, 'meta/eventName' ),
			$this->get_generic_map_fields( $feed, 'eventParameters', $form, $entry )
		);

		if ( array() === $user ) {
			$this->log_debug( __METHOD__ . '(): no identifier mapped, sending the event only.' );
		} else {
			$this->queue[ $form_id ][] = array(
				'type'  => 'user',
				'value' => $user,
			);
		}

		if ( array() !== $event ) {
			$this->queue[ $form_id ][] = array(
				'type'  => 'custom_event',
				'value' => array( $event ),
			);
		}

		return $entry;
	}

	/**
	 * The push rides the confirmation so it runs in the visitor's browser, where
	 * the SDK already knows who this device is. A redirect carries no markup.
	 *
	 * @param string|array<string, mixed> $confirmation
	 * @param array<string, mixed>        $form
	 *
	 * @return string|array<string, mixed>
	 */
	public function append_queue( $confirmation, $form ) {
		$form_id = (int) rgar( $form, 'id' );

		if ( ! is_string( $confirmation ) || empty( $this->queue[ $form_id ] ) ) {
			return $confirmation;
		}

		$pushes = '';

		foreach ( $this->queue[ $form_id ] as $push ) {
			$pushes .= sprintf( 'window.InsiderQueue.push(%s);', $this->encode( $push ) );
		}

		unset( $this->queue[ $form_id ] );

		return $confirmation . sprintf(
			'<script>window.InsiderQueue = window.InsiderQueue || [];%s</script>',
			$pushes
		);
	}

	/**
	 * Entry values reach a `<script>` block, so every character that could close
	 * it early is escaped as a unicode sequence.
	 *
	 * @param array<string, mixed> $data
	 */
	private function encode( array $data ): string {
		return (string) wp_json_encode(
			$data,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
		);
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
