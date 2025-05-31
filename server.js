const express = require('express');
const cors = require('cors');
const mysql = require('mysql2');
const app = express();

app.use(cors());
app.use(express.json());

// Connexion à la base "gestion_notes"
const db = mysql.createConnection({
  host: 'localhost',
  user: 'root',
  password: '', // Ajoute ton mot de passe si nécessaire
  database: 'gestion_notes'
});

// Ajouter un module
app.post('/add-module', (req, res) => {
  const { name } = req.body;
  if (!name) return res.status(400).send("Nom de module requis.");
  db.query('INSERT IGNORE INTO modules (name) VALUES (?)', [name], (err) => {
    if (err) return res.status(500).send(err);
    res.send('Module ajouté avec succès');
  });
});

// Ajouter un étudiant
app.post('/add-student', (req, res) => {
  const { nom, prenom, email, password, module } = req.body;
  if (!nom || !prenom || !email || !password || !module)
    return res.status(400).send("Tous les champs sont requis.");
  db.query(
    'INSERT INTO etudiants (nom, prenom, email, password, module) VALUES (?, ?, ?, ?, ?)',
    [nom, prenom, email, password, module],
    (err) => {
      if (err) return res.status(500).send(err);
      res.send('Étudiant ajouté avec succès');
    }
  );
});

app.listen(3000, () => {
  console.log('Serveur lancé sur http://localhost:3000');
});
 
function sendMessage() {
  const input = document.getElementById('userInput');
  if (input.value.trim() !== "") {
      const chatbox = document.querySelector('.chatbox');
      
      const userMessage = document.createElement('div');
      userMessage.className = 'message';
      userMessage.innerText = input.value;
      
      chatbox.appendChild(userMessage);
      input.value = "";

      // (Simuler une réponse de l'IA)
      setTimeout(() => {
          const iaResponse = document.createElement('div');
          iaResponse.className = 'message dark';
          iaResponse.innerText = "Réponse de l'IA...";
          chatbox.appendChild(iaResponse);
      }, 1000);
  }
}
function sendMessage() {
  const input = document.getElementById('userInput');
  if (input.value.trim() !== "") {
      const chatbox = document.querySelector('.chatbox');
      
      const userMessage = document.createElement('div');
      userMessage.className = 'message';
      userMessage.innerText = input.value;
      
      chatbox.appendChild(userMessage);
      input.value = "";

      // (Simuler une réponse de l'IA)
      setTimeout(() => {
          const iaResponse = document.createElement('div');
          iaResponse.className = 'message dark';
          iaResponse.innerText = "Réponse de l'IA...";
          chatbox.appendChild(iaResponse);
      }, 1000);
  }
}
