# ProPulse

🧩 Context del projecte

ProPulse és un sistema IoT de monitorització esportiva orientat a captar dades fisiològiques i de moviment en temps real.
El dispositiu (ESP32 + sensors) envia informació mitjançant WiFi cap a un servidor web, on les dades són emmagatzemades i visualitzades en un dashboard.

Flux general del sistema:

Sensors → ESP32 → WiFi → API/Servidor → Base de dades → Dashboard web

El projecte busca desenvolupar un prototip funcional, modular i escalable.

🎯 Abast del projecte
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

🟩 Fase 1 — Definició i anàlisi
🎯 Objectiu

Establir la base conceptual, establir el problema i determinar els requisits tècnics del sistema.

1. Definició del problema

Els sistemes comercials de monitorització esportiva són sovint costosos o massa tancats. ProPulse busca crear una alternativa portable, assequible i modulable, que permeti obtenir dades reals d’un esportista en temps real.

2. Objectius funcionals

Captar dades fisiològiques i de moviment.

Transmetre dades al servidor en temps real.

Emmagatzemar els entrenaments.

Mostrar les dades en gràfics i estadístiques.

Generar alertes per valors anòmals.

Permetre login i gestió d’usuaris.

3. Requisits tècnics
🔧 Maquinari

ESP32 amb WiFi.

Sensors:

MAX30102 (pulsacions),

MPU6050 (acceleració/IMU),

DS18B20 o DHT22 (temperatura).

Bateria recarregable / powerbank.

🖥️ Programari

Backend:

Flask (Python) o PHP

Base de dades:

MySQL

Frontend:

HTML5, CSS3, JavaScript

Gràfics amb Chart.js o Recharts

Control de versions:

Git + GitHub

🌐 Xarxa i protocols

Connexió via WiFi 2.4GHz

Protocols disponibles:

HTTP/REST (simple)

MQTT (lleuger i en temps real)

Format de dades:

{
  "ritme_cardiac": 120,
  "temperatura": 36.7,
  "ax": 0.12,
  "ay": -0.03,
  "az": 9.75
}

4. Diagrama de blocs (descripció)
[Sensors] → [ESP32] → (WiFi) → [Servidor/API] → [BD MySQL] → [Dashboard Web]

5. Lliurables

Document de requisits

Diagrama d’arquitectura

Esborrany de la memòria inicial

Repositori GitHub inicial

🟨 Fase 2 — Disseny del sistema
🎯 Objectiu

Definir l’arquitectura tècnica del sistema i establir l’estructura de dades, protocols i interfícies.

1. Arquitectura general
🔧 Hardware

Connexions I2C, 1-Wire i GPIO entre sensors i ESP32.

Esquema elèctric del prototip.

🌐 Xarxa

Selecció final de protocol: MQTT o REST.

Definició de rutes i payloads JSON.

Estratègies de seguretat bàsica (tokens simples, API key).

🗄️ Backend

Estructura de rutes:

/api/login

/api/sensor

/api/dades/{usuari}

Validació i sanitització de dades.

Control d’autenticació.

🎨 Frontend

Pàgines HTML5 semàntiques.

CSS3 responsive (media queries, flexbox).

Dashboard JS amb gràfics i taules.

2. Model de dades — Esquema ER
Taules principals:

usuaris

sessions_entrenament

dades_sensors

alertes (opcional)

Relacions:

usuari 1:N sessions

sessions 1:N dades_sensors

3. UX/UI

Wireframes del dashboard.

Interfície per:

inici de sessió,

vista general d’entrenaments,

gràfics en temps real,

historial d’usuari.

4. Proves preliminars

Connexió d’un sensor real a l’ESP32.

Enviament de dades de prova.

Validació d’insercions a MySQL.

5. Lliurables

Arquitectura tècnica documentada

Esquema ER + script SQL inicial

Prototip UX/UI

Informe de disseny i decisions
