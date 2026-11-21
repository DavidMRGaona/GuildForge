---
id: core.site.push-notifications
title: Avisos en tus dispositivos
summary: Cómo recibir los avisos de la web como notificaciones del móvil o del ordenador, también en iPhone, y cómo quitar un dispositivo.
category: account
audience: site
order: 42
keywords: [notificaciones, notificaciones push, push, avisos en el móvil, avisos en el ordenador, notificaciones del navegador, permitir notificaciones, bloquear notificaciones, iphone, ipad, pantalla de inicio, añadir a la pantalla de inicio, dispositivos, quitar dispositivo, aviso de prueba, no me llegan las notificaciones, sugerido]
related: [core.account.site-notifications, core.account.mail-preferences]
conditions:
  capabilities: [core.web_push]
---
Los avisos de la campana pueden llegarte también como notificaciones del sistema, en el móvil o en el ordenador, aunque tengas la web cerrada. Solo te llegan los que tú elijas, y solo en los dispositivos donde los actives.

## Activarlos en un dispositivo

1. Abre **Ajustes de la cuenta**, pestaña **Avisos**, desde el dispositivo donde quieras recibirlos.
2. En **Avisos en tus dispositivos**, pulsa **Recibir avisos en este dispositivo**.
3. El navegador te pregunta si permites las notificaciones de {{site_name}}. Acepta.

El navegador solo pregunta al pulsar el botón. Si no lo pulsas, nunca te pedirá permiso.

## Elegir qué avisos te llegan

Activar un dispositivo no basta: además, elige qué avisos quieres recibir. En la misma pestaña, cada tipo de aviso que sale en la campana tiene un interruptor más, **En tus dispositivos**. Está apagado en todos hasta que lo enciendas, y vale para todos los dispositivos que hayas activado.

- Los marcados como **Sugerido** suelen merecer la pena, como las propuestas que esperan tu revisión. **Activar las sugeridas** los enciende de una vez. Después pulsa **Guardar cambios**.
- Solo te llega lo que también ves en la campana. Si apagas **En la web**, ese aviso tampoco te llega a los dispositivos.
- Si silencias algo concreto desde un correo, dejas de recibir también sus notificaciones.
- Los avisos obligatorios, con candado, siguen llegando por la campana y por correo. No se mandan como notificación.

## En iPhone y iPad

Safari solo manda notificaciones a una web que has añadido a la pantalla de inicio:

1. Abre {{site_name}} en Safari.
2. Pulsa **Compartir** y después **Añadir a la pantalla de inicio**.
3. Abre la web desde el icono nuevo, inicia sesión y activa los avisos como arriba.

Hace falta iOS o iPadOS 16.4 o posterior.

## Probar que llegan

Con este dispositivo activado, pulsa **Mandar un aviso de prueba**. En unos segundos te llegará una notificación «Aviso de prueba», aunque tengas la web abierta delante.

Las notificaciones normales no salen mientras miras la web: si tienes una pestaña de {{site_name}} abierta delante, el aviso ya sale en la campana.

## Quitar un dispositivo

En **Dispositivos que reciben avisos** ves cada navegador que los recibe, con su último uso: la última vez que le llegó un aviso o que abriste la web en él. **Este dispositivo** es desde el que miras la página. Pulsa **Quitar** para que deje de recibirlos.

Un dispositivo también deja de recibirlos al cerrar sesión en él, y cuando lleva 90 días sin usarse.

## Qué viaja en la notificación

El servicio de notificaciones del navegador (Google, Mozilla, Apple o Microsoft) no ve de qué trata el aviso: solo recibe un número cifrado. Tu dispositivo pide el aviso a {{site_name}} con tu sesión y lo muestra.

## Si algo no sale bien

- **No veo el botón**: este sitio no tiene activadas las notificaciones, o tu navegador no puede recibirlas. Prueba con otro navegador.
- **Dice que las he bloqueado**: las rechazaste al preguntar. Permítelas en los ajustes del sitio de tu navegador (el icono junto a la dirección) y recarga la página.
- **El aviso de prueba no llega**: revisa que el sistema no tenga silenciadas las notificaciones del navegador, por ejemplo en el modo «No molestar».
- **Me llega «Tienes un aviso nuevo» sin más**: el dispositivo no ha podido leer el aviso, por ejemplo porque se cerró tu sesión en él. Púlsalo para ver tus avisos.

[[action:profile.show?tab=mail|Ir a la pestaña Avisos]]
