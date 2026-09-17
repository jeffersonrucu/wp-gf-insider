=== Insider for Gravity Forms ===
Contributors: jeffersonrucu
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Envia os envios do Gravity Forms para a Insider pela API de upsert e publica a tag da Insider.

== Description ==

Add-on de feed do Gravity Forms. Cada formulário ganha um feed onde se escolhe,
pelo painel, qual campo vira o e-mail, o telefone, o nome e o identificador do
contato na Insider e quais campos viram parâmetros do evento.

O envio sai do servidor, pela API de upsert, no mesmo formato que o back-end usa:
o CPF em `identifiers`, nome e contato em `attributes` e os dados do formulário em
`events[].event_params.custom`. Um envio com falha vira nota na entrada.

O plugin também publica a tag da Insider no `<head>` e a protege do atraso e da
minificação do WP Rocket e do Perfmatters, que servem uma cópia local do SDK.

== Installation ==

1. Configure o nome, o ID do parceiro e a chave da API em Formulários › Configurações › Insider.
2. Em cada formulário, abra Configurações › Insider e crie um feed.

== Changelog ==

= 1.1.2 =
* Identificador próprio, como o CPF, vai em identifiers.custom: na raiz a Insider recusa o usuário.
* Recusa respondida com 200 vira nota de erro na entrada.

= 1.1.1 =
* A chamada à API sai por IPv4: a lista de IPs da chave da Insider não aceita IPv6, e o servidor com IPv6 recebia 403.

= 1.1.0 =
* Envio pela API de upsert, no formato do back-end: CPF em identifiers e dados do formulário no evento.
* Sai o mapa de atributos do contato; os campos vão em parâmetros do evento.

= 1.0.0 =
* Versão inicial.
