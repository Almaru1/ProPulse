# ProPulse

## Context del projecte

ProPulse és un sistema IoT de monitorització esportiva orientat a captar dades fisiològiques i de moviment en temps real.

El dispositiu (ESP32 + sensors) envia informació mitjançant WiFi cap a un servidor web, on les dades són emmagatzemades i visualitzades en un dashboard.

Flux general del sistema:

Sensors → ESP32 → WiFi → API/Servidor → Base de dades → Dashboard web

El projecte busca desenvolupar un prototip funcional, modular i escalable.

## Abast del projecte

### Inclou

* Integració de sensors (pulsacions, acceleració, temperatura, velocitat)
* Microcontrolador ESP32 per lectura i transmissió de dades
* Comunicació via WiFi amb protocols HTTP/REST o MQTT
* Backend amb Flask (Python) o PHP
* Base de dades MySQL
* Dashboard web en HTML5, CSS3 i JavaScript
* Gràfics amb Chart.js o Recharts
* Autenticació bàsica d’usuaris
* Documentació tècnica i diagrames del sistema

### No inclou

* Aplicacions mòbils natives
* Intel·ligència artificial avançada
* Hardware certificat per ús mèdic
* Comunicacions mòbils (4G/5G)

# Fase 1 — Definició i anàlisi

## Objectiu

Establir la base conceptual, definir el problema i determinar els requisits tècnics del sistema.

## Definició del problema

Els sistemes comercials de monitorització esportiva són sovint costosos o massa tancats.

ProPulse busca crear una alternativa portable, assequible i modulable, que permeti obtenir dades reals d’un esportista en temps real.

## Objectius funcionals

1. Captar dades fisiològiques i de moviment
2. Transmetre dades al servidor en temps real
3. Emmagatzemar els entrenaments
4. Mostrar les dades en gràfics i estadístiques
5. Generar alertes per valors anòmals
6. Permetre login i gestió d’usuaris

## Requisits tècnics

### Maquinari

* ESP32 amb WiFi
* Sensors:
  * MAX30102 (pulsacions)
  * MPU6050 (acceleració / IMU)
  * DS18B20 o DHT22 (temperatura)
* Bateria recarregable o powerbank

### Programari

* Backend:
  * Flask (Python) o PHP
* Base de dades:
  * MySQL
* Frontend:
  * HTML5
  * CSS3
  * JavaScript
* Gràfics:
  * Chart.js o Recharts
* Control de versions:
  * Git i GitHub

### Xarxa i protocols

* Connexió via WiFi 2.4 GHz
* Protocols disponibles:
  * HTTP/REST (simple)
  * MQTT (lleuger i en temps real)

Format de dades (exemple):

```json
{ "ritme_cardiac": 120, "temperatura": 36.7, "ax": 0.12, "ay": -0.03, "az": 9.75 }


