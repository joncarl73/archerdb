@echo off
setlocal enableextensions enabledelayedexpansion

REM ========= Config =========
REM Pass your local base URL as the first arg, or it defaults to http://archerdb.test
set BASE_URL=%1
if "%BASE_URL%"=="" set BASE_URL=http://archerdb.test

REM Events we care about for leagues + Pro subscriptions
set EVENTS=checkout.session.completed,payment_intent.succeeded,payment_intent.payment_failed,checkout.session.async_payment_succeeded,checkout.session.async_payment_failed,charge.succeeded,charge.refunded,customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.paid,invoice.payment_failed,account.updated

echo.
echo ----------------------------------------------------------------------
echo  Stripe CLI: Listening for webhooks
echo  Forwarding PLATFORM and CONNECT events to:
echo      %BASE_URL%/stripe/webhook
echo  Events:
echo      %EVENTS%
echo  (Press Ctrl+C to stop)
echo ----------------------------------------------------------------------
echo.

stripe listen ^
  --forward-to %BASE_URL%/stripe/webhook ^
  --forward-connect-to %BASE_URL%/stripe/webhook ^
  --events %EVENTS%
