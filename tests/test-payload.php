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

// Custom identifiers: o CPF entra como identificador próprio, não no uuid.
check(
	'identificador próprio',
	GF_Insider_Payload::user(
		array( 'name' => 'Maria da Silva' ),
		array( 'gdpr_optin' => '1' ),
		array(),
		array( 'cpf' => '529.982.247-25' )
	),
	array(
		'name'               => 'Maria',
		'surname'            => 'da Silva',
		// Sem pontuação: é assim que o back-end do cliente manda o mesmo CPF.
		'custom_identifiers' => array( 'cpf' => '52998224725' ),
		'gdpr_optin'         => true,
	)
);

check(
	'identificador não numérico fica como veio',
	GF_Insider_Payload::user( array(), array(), array(), array( 'matricula' => 'AB-123' ) ),
	array( 'custom_identifiers' => array( 'matricula' => 'AB-123' ) )
);

// Número continua número: como string a Insider não segmenta por faixa.
check(
	'atributo numérico',
	GF_Insider_Payload::user( array( 'email' => 'a@b.com.br' ), array(), array( 'valor' => '15000', 'parcelas' => '12' ) ),
	array( 'email' => 'a@b.com.br', 'custom' => array( 'valor' => 15000, 'parcelas' => 12 ) )
);

check(
	'decimal',
	GF_Insider_Payload::user( array( 'email' => 'a@b.com.br' ), array(), array( 'valor' => '458.33' ) ),
	array( 'email' => 'a@b.com.br', 'custom' => array( 'valor' => 458.33 ) )
);

// Zero à esquerda é código, não quantidade.
check(
	'CEP não vira número',
	GF_Insider_Payload::user( array( 'email' => 'a@b.com.br' ), array(), array( 'cep' => '01310' ) ),
	array( 'email' => 'a@b.com.br', 'custom' => array( 'cep' => '01310' ) )
);

check(
	'identificador vazio não identifica',
	GF_Insider_Payload::user( array( 'name' => 'Maria da Silva' ), array(), array(), array( 'cpf' => '' ) ),
	array()
);

if ( 0 === $failures ) {
	echo "ok\n";
	exit( 0 );
}

printf( "%d verificação(ões) falharam\n", $failures );
exit( 1 );
