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
check( 'celular com máscara', GF_Insider_Payload::phone( '(31) 9 8888-7777' ), '+5531988887777' );
check( 'fixo com máscara', GF_Insider_Payload::phone( '(31) 3333-4444' ), '+553133334444' );
check( 'já em E.164', GF_Insider_Payload::phone( '+55 31 98888-7777' ), '+5531988887777' );
check( 'curto demais', GF_Insider_Payload::phone( '98888-7777' ), '' );
check( 'vazio', GF_Insider_Payload::phone( '' ), '' );

// Name: one field holds the full name in every form of this site.
check( 'nome completo', GF_Insider_Payload::name_parts( 'Maria  da Silva ' ), array( 'Maria', 'da Silva' ) );
check( 'nome único', GF_Insider_Payload::name_parts( 'Maria' ), array( 'Maria', '' ) );

// Consent: Gravity Forms writes '1' when checked and '0' when not.
check( 'consentimento marcado', GF_Insider_Payload::boolean( '1' ), true );
check( 'consentimento desmarcado', GF_Insider_Payload::boolean( '0' ), false );
check( 'consentimento ausente', GF_Insider_Payload::boolean( '' ), false );

// User: identifiers, opt-ins and custom attributes.
check(
	'contato completo',
	GF_Insider_Payload::user(
		array(
			'email'        => 'maria@exemplo.com.br',
			'phone_number' => '(31) 9 8888-7777',
			'name'         => 'Maria da Silva',
		),
		array( 'gdpr_optin' => '1' ),
		array( 'assunto' => 'Simulação', 'vazio' => '' )
	),
	array(
		'email'        => 'maria@exemplo.com.br',
		'phone_number' => '+5531988887777',
		'name'         => 'Maria',
		'surname'      => 'da Silva',
		'gdpr_optin'   => true,
		'custom'       => array( 'assunto' => 'Simulação' ),
	)
);

check(
	'e-mail inválido é descartado',
	GF_Insider_Payload::user( array( 'email' => 'maria(arroba)exemplo', 'uuid' => '12345678909' ) ),
	array( 'uuid' => '12345678909' )
);

check(
	'sem identificador não vai',
	GF_Insider_Payload::user( array( 'name' => 'Maria da Silva' ) ),
	array()
);

check(
	'sobrenome mapeado não é sobrescrito',
	GF_Insider_Payload::user( array( 'uuid' => 'x1', 'name' => 'Maria da Silva', 'surname' => 'Souza' ) ),
	array( 'uuid' => 'x1', 'name' => 'Maria da Silva', 'surname' => 'Souza' )
);

// Event.
check(
	'evento com parâmetros',
	GF_Insider_Payload::event( 'form_submit', array( 'form_name' => 'Contato' ) ),
	array( 'event_name' => 'form_submit', 'event_parameters' => array( 'custom' => array( 'form_name' => 'Contato' ) ) )
);
check( 'evento sem nome', GF_Insider_Payload::event( '  ' ), array() );

if ( 0 === $failures ) {
	echo "ok\n";
	exit( 0 );
}

printf( "%d verificação(ões) falharam\n", $failures );
exit( 1 );
