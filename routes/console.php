<?php

/*
|--------------------------------------------------------------------------
| Comandos de consola
|--------------------------------------------------------------------------
|
| Los comandos propios del sistema viven en app/Console/Commands y Laravel los
| registra solo (hoy: `usuario:password`, la entrega manual de credenciales
| cuando no hay servidor de correo).
|
| No hay tareas programadas: el worker de la carga masiva no se agenda aquí sino
| en el cron del servidor, porque tiene que sobrevivir a cada despliegue.
| Ver deploy/RUNBOOK.md, §6.
|
*/
