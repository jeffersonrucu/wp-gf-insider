=== Insider for Gravity Forms ===
Contributors: jeffersonrucu
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Envia os envios do Gravity Forms para a Insider pela fila do SDK web e publica a tag da Insider.

== Description ==

Add-on de feed do Gravity Forms. Cada formulário ganha um feed onde se escolhe,
pelo painel, qual campo vira o e-mail, o telefone, o nome e o identificador do
contato na Insider, quais campos viram atributos do contato e quais viram
parâmetros do evento.

O envio acontece no navegador de quem preencheu, pela `window.InsiderQueue`, de
modo que o lead fica ligado à sessão que a Insider já reconhece — o que a API
server-side não faz. Os valores vêm do servidor, então um formulário de várias
etapas envia todos os campos, e não só os da última tela.

O plugin também publica a tag da Insider no `<head>` e a protege do atraso e da
minificação do WP Rocket e do Perfmatters, que servem uma cópia local do SDK.

== Installation ==

1. Configure o nome e o ID do parceiro em Formulários › Configurações › Insider.
2. Em cada formulário, abra Configurações › Insider e crie um feed.

== Changelog ==

= 1.0.0 =
* Versão inicial.
