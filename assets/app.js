import './bootstrap.js';
// import './styles/first_page.css';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */




console.log('First page loaded');
// assets/app.js
const socket = new WebSocket('ws://localhost:8080');

socket.onopen = function(e) {
    console.log('Connected to WebSocket server');
};

socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
    // Handle message display in your UI
};

function sendMessage(message) {
    socket.send(JSON.stringify({
        message: message
    }));
}