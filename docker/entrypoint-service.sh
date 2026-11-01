#!/command/with-contenv sh
# The application, as the kernel's template starts it: `winter-entrypoint` from the
# image — opcache off and hot reload with DEV=true, otherwise `call di build` first and
# a refusal to start if it fails. with-contenv is required: s6 starts services with an
# empty environment, so SERVER_PORT, DEV and WINTER_KEY would not reach it without it.
#
# su-exec execs in place, so the application is a direct child of s6-supervise and gets
# its SIGTERM; it stops its own components. Logs go to stdout.
exec su-exec winter winter-entrypoint
