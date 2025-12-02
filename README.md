# ProPulse

Context del projecte

ProPulse és un sistema IoT de monitorització esportiva orientat a captar dades fisiològiques i de moviment en temps real.
El dispositiu (ESP32 + sensors) envia informació mitjançant WiFi cap a un servidor web, on les dades són emmagatzemades i visualitzades en un dashboard.

Flux general del sistema:

Sensors → ESP32 → WiFi → API/Servidor → Base de dades → Dashboard web

El projecte busca desenvolupar un prototip funcional, modular i escalable.

Abast del projecte
· Inclou

Integració de sensors (pulsacions, acceleració, temperatura, velocitat).

Microcontrolador ESP32 per lectura i transmissió de dades.

Comunicació via WiFi amb protocols HTTP/REST o MQTT.

Backend amb Flask o PHP.

Base de dades MySQL.

Dashboard web en HTML5/CSS3/JS.

Autenticació bàsica d’usuaris.

Documentació tècnica i diagrames del sistema.

· No inclou

Aplicacions mòbils natives.

Intel·ligència artificial avançada.

Hardware certificat per ús mèdic.

Comunicacions mòbils (4G/5G).

