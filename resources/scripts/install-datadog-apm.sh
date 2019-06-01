if ! php -m | grep -q ddtrace; then
    wget -O datadog-php-tracer.deb https://github.com/DataDog/dd-trace-php/releases/download/0.25.0/datadog-php-tracer_0.25.0_amd64.deb
    dpkg -i datadog-php-tracer.deb
    rm datadog-php-tracer.deb
fi
