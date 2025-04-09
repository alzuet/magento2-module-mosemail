# Módulo de envío de correos
Permite enviar correos mediante un API Key de Bravo.
Permite atrapar los correos de la tienda para que no lleguen al destino.

1. Para instalar el módulo 

```bash 
composer require atelier/mosemail
```

2. Habilitar el módulo

```bash 
bin/magento module:enable Atelier_EmailSender
```

3. Actualizar Magento

```bash 
bin/magento setup:upgrade
```
4. Configurar el módulo
General > Envío de Correcto > Habilitar	= Yes	
Informar la API Key de Brevo		
Si se desean atrapar los correos, poner Atrapa los correos = Yes
Informar el email que atrapará los correos.
Borrar la caché de Magento, desde el propio admin.
Asegurar que llega un email al email que "atrapa".