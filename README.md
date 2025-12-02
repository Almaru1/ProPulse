# ProPulse

🎯 Context del projecte ProPulse:

ProPulse és un sistema IoT orientat a la monitorització esportiva en temps real. Integra sensors fisiològics i de moviment amb un microcontrolador (ESP32/Arduino) que transmet les dades via WiFi a un servidor web, on són emmagatzemades, processades i visualitzades en forma de gràfiques i estadístiques.

El projecte combina:

Hardware (sensors + ESP32)

Xarxes i protocols (WiFi, MQTT o HTTP)

Backend (API REST amb Flask o PHP, BD MySQL)

Frontend (HTML5, CSS3, JS i gràfics)

Anàlisi de dades (valors reals i històrics, alertes)

L’objectiu final és crear un prototip funcional complet que mostri tot el flux: captura → transmissió → emmagatzematge → visualització.

🎯 Abast del projecte
Inclou

Desenvolupament del dispositiu IoT amb ESP32/Arduino.

Integració de sensors: pulsòmetre, acceleròmetre/IMU, temperatura, velocitat.

Comunicació sense fils mitjançant WiFi.

Enviament de dades via MQTT o REST API.

Creació d’una base de dades MySQL.

Backend per rebre, validar i guardar dades.

Interfície web responsive amb gràfics.

Sistema bàsic d’autenticació d’usuaris.

Documentació tècnica, diagrames i proves.

No inclou

Aplicacions mòbils natives.

Processament avançat d’IA o machine learning.

Maquinari certificat professional.

Comunicacions 4G/5G o Bluetooth avançat.

🟩 Fase 1 — Definició i anàlisi
🎯 Objectiu

Establir la base conceptual del projecte i determinar tots els requisits necessaris.

1. Definició del problema

Els sistemes de monitorització esportiva són sovint costosos o limitats. ProPulse pretén crear un sistema modular, assequible i ampliable que permeti controlar valors esportius en temps real.

2. Objectius funcionals

Captar dades fisiològiques i de moviment.

Transmetre dades en temps real a un servidor.

Emmagatzemar l’històric d’entrenaments.

Visualitzar gràfics i estadístiques a la web.

Detectar valors fora de rang (alertes).

Permetre accés autenticat d’usuaris.

3. Requisits tècnics
• Maquinari

ESP32 amb WiFi integrat.

Sensors: MAX30102 (pulsacions), MPU6050 (acceleració), DHT22/DS18B20 (temperatura).

Bateria o font d’alimentació portàtil.

• Programari

Backend: Flask o PHP.

Base de dades: MySQL.

Frontend: HTML5, CSS3, JavaScript (referències tècniques com HTML5 i CSS3 procedents del manual HTML5 ).

Control de versions amb Git i GitHub.

• Xarxa i protocols

WiFi 2.4GHz.

Protocols candidats:

HTTP/REST per simplicitat,

MQTT per comunicació contínua i lleugera.

4. Diagrama de blocs (descripció)
[Sensors] → [ESP32] → (WiFi) → [Servidor/API] → [Base de dades MySQL] → [Web Dashboard]

5. Lliurables

Document de requisits funcionals i tècnics.

Diagrama del sistema.

Esborrany de la memòria tècnica inicial.

Repositori GitHub estructurat.

🟨 Fase 2 — Disseny del sistema
🎯 Objectiu

Definir l’arquitectura completa del sistema i establir l’estructura tècnica que es desenvoluparà posteriorment.

1. Arquitectura del sistema
• Hardware

Connexió dels sensors a l’ESP32 (I2C, 1-Wire, GPIO).

Esquema elèctric preliminar.

• Xarxa

Selecció definitiva del protocol (MQTT/HTTP).

Definició del format de les trames JSON.

• Backend

Arquitectura de l’API REST.

Rutes principals (exemple: /api/sensor, /api/login).

Validació de dades.

Gestió d’usuaris i sessions.

• Frontend

Estructura HTML5 semàntica (segons bones pràctiques del manual HTML5 ).

Disseny responsive amb CSS3.

Dashboard interactiu amb JavaScript.

2. Disseny de la base de dades (ER)

Basada en coneixements de MySQL i gestionada segons les bones pràctiques dels documents Bases de Dades i Guia MySQL II .

Taules proposades:

usuaris

sessions_entrenament

dades_sensors

alertes (opcional)

Relacions 1:N entre sessions i dades, i entre usuaris i sessions.

3. UX/UI

Wireframes de les vistes principals.

Prototips en baixa fidelitat del dashboard i gestió d’usuaris.

Definició de paleta de colors i components.

4. Protocols
Exemple de payload JSON:
{
  "ritme_cardiac": 120,
  "temperatura": 36.7,
  "ax": 0.12,
  "ay": -0.03,
  "az": 9.75
}

5. Proves inicials

Connexió d’un sensor a l’ESP32.

Enviament d’una lectura de prova al backend.

Comprovació d’inserció a la base de dades.

6. Lliurables

Dossier d’arquitectura tècnica complet.

Esquema ER + script SQL inicial.

Prototip UX/UI.

Informe de decisions i proves preliminars.
