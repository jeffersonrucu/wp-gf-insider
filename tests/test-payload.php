<?php
/**
 * Self-check for the payload rules. Run it from the plugin folder:
 * php tests/test-payload.php
 */

define( 'WPINC', 'wp-includes' );

require_once __DIR__ . '/../includes/class-gf-insider-payload.php';

$failures = 0;

function check( string $label, $actual, $expected ): void {
	global $failures;

	if ( $actual === $expected ) {
		return;
	}

	++$failures;

	printf( "FALHOU %s\n  esperado: %s\n  obtido:   %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) );
}

// Phone: the Brazilian masks the forms use, and what must not pass.
check( 'celular com máscara', GF_Insider_Payload::phone( '(11) 90000-0000' ), '+5511900000000' );
check( 'fixo com máscara', GF_Insider_Payload::phone( '(11) 3000-0000' ), '+551130000000' );
check( 'já em E.164', GF_Insider_Payload::phone( '+55 11 90000-0000' ), '+5511900000000' );
check( 'curto demais', GF_Insider_Payload::phone( '90000-0000' ), '' );
check( 'vazio', GF_Insider_Payload::phone( '' ), '' );

// Name: one field holds the full name in every form of this site.
check( 'nome completo', GF_Insider_Payload::name_parts( 'Jefferson  da Silva ' ), array( 'Jefferson', 'da Silva' ) );
check( 'nome único', GF_Insider_Payload::name_parts( 'Jefferson' ), array( 'Jefferson', '' ) );

// Consent: Gravity Forms writes '1' when checked and '0' when not.
check( 'consentimento marcado', GF_Insider_Payload::boolean( '1' ), true );
check( 'consentimento desmarcado', GF_Insider_Payload::boolean( '0' ), false );
check( 'consentimento ausente', GF_Insider_Payload::boolean( '' ), false );

check( 'data do GF em UTC', GF_Insider_Payload::timestamp( '2026-01-20 20:50:00' ), '2026-01-20T20:50:00.000Z' );

// The client's example: CPF in identifiers, first name only in name, data in the event.
$event = GF_Insider_Payload::event(
	'lead_simulador',
	'2026-01-20T20:50:00.000Z',
	array( 'valor_emprestimo' => '5000.00', 'numero_parcelas' => '12', 'vazio' => '' )
);

check(
	'simulador no formato do cliente',
	GF_Insider_Payload::user(
		array(
			'name'         => 'Fulano de Tal',
			'phone_number' => '(11) 90000-0000',
		),
		array( 'gdpr_optin' => '1' ),
		array( 'cpf' => '000.000.000-00' ),
		array( $event )
	),
	array(
		'identifiers' => array( 'cpf' => '00000000000' ),
		'attributes'  => array(
			'name'         => 'Fulano',
			'surname'      => 'de Tal',
			'phone_number' => '+5511900000000',
			'gdpr_optin'   => true,
		),
		'events'      => array(
			array(
				'event_name'   => 'lead_simulador',
				'timestamp'    => '2026-01-20T20:50:00.000Z',
				'event_params' => array( 'custom' => array( 'valor_emprestimo' => 5000.0, 'numero_parcelas' => 12 ) ),
			),
		),
	)
);

check(
	'uuid entra junto do CPF',
	GF_Insider_Payload::user( array( 'uuid' => '0000', 'email' => 'fulano@exemplo.com.br' ), array(), array( 'cpf' => '00000000000' ) ),
	array(
		'identifiers' => array( 'cpf' => '00000000000', 'uuid' => '0000' ),
		'attributes'  => array( 'email' => 'fulano@exemplo.com.br' ),
	)
);

check(
	'sem CPF, o e-mail identifica',
	GF_Insider_Payload::user( array( 'name' => 'Jefferson', 'email' => 'jefferson@exemplo.com.br', 'phone_number' => '(11) 90000-0000' ) ),
	array(
		'identifiers' => array( 'email' => 'jefferson@exemplo.com.br' ),
		'attributes'  => array( 'name' => 'Jefferson', 'email' => 'jefferson@exemplo.com.br', 'phone_number' => '+5511900000000' ),
	)
);

check(
	'sem CPF nem e-mail, o telefone identifica',
	GF_Insider_Payload::user( array( 'name' => 'Jefferson', 'phone_number' => '(11) 90000-0000' ) ),
	array(
		'identifiers' => array( 'phone_number' => '+5511900000000' ),
		'attributes'  => array( 'name' => 'Jefferson', 'phone_number' => '+5511900000000' ),
	)
);

check(
	'e-mail inválido é descartado',
	GF_Insider_Payload::user( array( 'email' => 'jefferson(arroba)exemplo', 'uuid' => 'x1' ) ),
	array( 'identifiers' => array( 'uuid' => 'x1' ) )
);

check( 'sem identificador não vai', GF_Insider_Payload::user( array( 'name' => 'Jefferson da Silva' ) ), array() );
check( 'identificador vazio não identifica', GF_Insider_Payload::user( array( 'name' => 'Jefferson' ), array(), array( 'cpf' => '' ) ), array() );

check(
	'sobrenome mapeado não é sobrescrito',
	GF_Insider_Payload::user( array( 'uuid' => 'x1', 'name' => 'Jefferson da Silva', 'surname' => 'Souza' ) ),
	array( 'identifiers' => array( 'uuid' => 'x1' ), 'attributes' => array( 'name' => 'Jefferson da Silva', 'surname' => 'Souza' ) )
);

check(
	'identificador não numérico fica como veio',
	GF_Insider_Payload::user( array(), array(), array( 'matricula' => 'AB-123' ) ),
	array( 'identifiers' => array( 'matricula' => 'AB-123' ) )
);

check( 'evento sem nome', GF_Insider_Payload::event( '  ', '2026-01-20T20:50:00.000Z' ), array() );

// Leading zero is a code, not a quantity.
check(
	'CEP não vira número',
	GF_Insider_Payload::event( 'e', 't', array( 'cep' => '01310', 'cnpj' => '00.000.000/0000-00' ) ),
	array( 'event_name' => 'e', 'timestamp' => 't', 'event_params' => array( 'custom' => array( 'cep' => '01310', 'cnpj' => '00.000.000/0000-00' ) ) )
);

if ( 0 === $failures ) {
	echo "ok\n";
	exit( 0 );
}

printf( "%d verificação(ões) falharam\n", $failures );
exit( 1 );
