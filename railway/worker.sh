#!/bin/sh
# Queue-worker voor de Outlook-koppeling (ADR-011). Draai dit als tweede Railway-service
# naast de webservice, met dezelfde omgevingsvariabelen. Zonder worker blijven afspraken op
# "Wordt nu in Outlook gezet" staan.
#
# --max-time: worker herstart elk uur, zodat een nieuwe deploy of gewijzigde config wordt opgepikt.
exec php artisan queue:work --sleep=3 --tries=5 --max-time=3600
