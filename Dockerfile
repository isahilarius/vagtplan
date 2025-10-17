# Brug en officiel PHP image med Apache
FROM php:8.2-apache

# Installer nødvendige pakker (Node, npm, SQLite)
RUN apt-get update && apt-get install -y \
    nodejs \
    npm \
    sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Aktivér mod_rewrite
RUN a2enmod rewrite

# Sæt arbejdsmappe
WORKDIR /var/www/html

# Kopiér alle projektfiler
COPY . .

# Installer Tailwind og byg CSS
RUN npm install
RUN npx tailwindcss -i ./input.css -o ./dist/output.css

# Giv PHP og SQLite de rigtige rettigheder
RUN chmod 777 /var/www/html/vagtskema.db || true

# Eksponér standard Apache-port
EXPOSE 80

# Start Apache-serveren
CMD ["apache2-foreground"]
