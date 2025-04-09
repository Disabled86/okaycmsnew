<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат для проверки остатков</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f5f5f5;
        }

        /* Контейнер чата */
        .chat-container {
            width: 360px;
            max-width: 100%;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: height 0.3s ease-in-out;
            height: 60px; /* Начальная высота */
            display: flex;
            flex-direction: column;
        }

        /* Заголовок (кнопка) */
        .chat-header {
            background: #007bff;
            color: #fff;
            padding: 15px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Область сообщений */
        .messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            border-bottom: 1px solid #ddd;
            display: none; /* Скрыто изначально */
        }

        /* Сообщение */
        .message {
            margin-bottom: 10px;
            align-self: flex-start; /* Все сообщения выровнены по левому краю */
            max-width: 100%; /* Занимают всю ширину */
        }

        /* Имя отправителя */
        .sender-name {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }

        /* Имя бота */
        .bot-message .sender-name {
            color: #007bff; /* Синий цвет для бота */
        }

        /* Имя пользователя */
        .user-message .sender-name {
            color: #28a745; /* Зелёный цвет для пользователя */
        }

        /* Текст сообщения */
        .message-text {
            padding: 10px;
            border-radius: 10px; /* Закруглённые углы */
            word-break: break-word; /* Автоматический перенос строк */
        }

        /* Приветственное сообщение и ответы бота */
        .bot-message .message-text {
            background: #f0f0f0; /* Серый фон */
        }

        /* Центрирование приветственного сообщения */
        .welcome-message {
            text-align: center; /* Центрируем текст */
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
        }

        /* Сообщение пользователя */
        .user-message .message-text {
            background: #dcf8c6; /* Зелёный фон */
        }

        /* Ввод сообщения */
        .chat-input {
            display: flex;
            padding: 10px;
            background: #fff;
            display: none; /* Скрыто изначально */
        }

        .chat-input input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 20px; /* Закруглённые углы */
            outline: none;
            font-size: 14px;
        }

        .chat-input button {
            margin-left: 10px;
            padding: 10px 15px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 20px; /* Закруглённые углы */
            cursor: pointer;
            font-size: 14px;
        }

        .chat-input button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Заголовок (кнопка) -->
        <div class="chat-header" id="chat-header">Проверка остатков</div>

        <!-- Область сообщений -->
        <div class="messages" id="messages"></div>

        <!-- Ввод сообщения -->
        <div class="chat-input" id="chat-input">
            <input type="text" id="user-input" placeholder="Введите артикул товара" />
            <button id="send-btn">Отправить</button>
        </div>
    </div>

    <script>
        const chatHeader = document.getElementById('chat-header');
        const messagesContainer = document.getElementById('messages');
        const chatInput = document.getElementById('chat-input');

        let isChatExpanded = false;

        // Функция для добавления сообщений
        function addMessage(sender, message, className) {
            const messageWrapper = document.createElement('div');
            messageWrapper.className = `message ${className}`;

            // Создаем имя отправителя
            const senderName = document.createElement('div');
            senderName.className = 'sender-name';
            senderName.textContent = sender; // "Бот" или "Вы"

            // Создаем текст сообщения
            const messageText = document.createElement('div');
            messageText.className = 'message-text';
            messageText.innerHTML = message; // Используем innerHTML для поддержки <br>

            // Добавляем имя и текст в общий контейнер
            messageWrapper.appendChild(senderName);
            messageWrapper.appendChild(messageText);

            messagesContainer.appendChild(messageWrapper);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Обработчик клика на заголовке
        chatHeader.addEventListener('click', () => {
            if (!isChatExpanded) {
                // Расширяем чат
                document.querySelector('.chat-container').style.height = '450px';
                messagesContainer.style.display = 'block';
                chatInput.style.display = 'flex';

                // Добавляем приветственное сообщение
                if (messagesContainer.children.length === 0) {
                    const welcomeMessage = document.createElement('div');
                    welcomeMessage.className = 'welcome-message';
                    welcomeMessage.innerHTML =
                        'Уважаемые покупатели!<br>' +
                        'Добро пожаловать в наш чат-бот.<br>' +
                        'В данный момент бот предоставляет информацию только об остатках товаров для освещения.<br>' +
                        'Для проверки наличия товара введите его артикул (как на сайте).';
                    messagesContainer.appendChild(welcomeMessage);
                }

                // Меняем текст заголовка на "Свернуть"
                chatHeader.textContent = 'Свернуть';
                isChatExpanded = true;
            } else {
                // Сворачиваем чат
                document.querySelector('.chat-container').style.height = '60px';
                messagesContainer.style.display = 'none';
                chatInput.style.display = 'none';

                // Меняем текст заголовка обратно на "Проверка остатков"
                chatHeader.textContent = 'Проверка остатков';
                isChatExpanded = false;
            }
        });

        // Отправка запроса
        const userInput = document.getElementById('user-input');
        const sendButton = document.getElementById('send-btn');

        function sendMessage() {
            const article = userInput.value.trim();
            if (!article) {
                alert('Введите артикул товара.');
                return;
            }

            // Добавляем сообщение пользователя
            addMessage('Вы', article, 'user-message');

            // Отправляем запрос на сервер
            fetch('db.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `article=${encodeURIComponent(article)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Проверяем количество товара
                    const totalStock = data.total_stock;
                    let stockMessage = '';
                    if (totalStock < 3) {
                        stockMessage = 'Количество ограничено, свяжитесь с менеджером.';
                    } else if (totalStock > 100) {
                        stockMessage = 'В наличии более 100 шт.';
                    }
					else if (totalStock > 50) {
                        stockMessage = 'В наличии более 50 шт.';
                    }else if (totalStock > 40) {
                        stockMessage = 'В наличии более 40 шт.';
                    }
					else if (totalStock > 30) {
                        stockMessage = 'В наличии более 30 шт.';
                    }
					  else if (totalStock > 20) {
                        stockMessage = 'В наличии более 20 шт.';
                    }
					else if (totalStock > 10) {
                        stockMessage = 'В наличии более 10 шт.';
                    }
					else if (totalStock > 5) {
                      stockMessage = 'В наличии более 5 шт.';
					}
					else {
                        stockMessage = `Общий остаток: ${totalStock} шт.`;
                    }

                    addMessage('Бот', stockMessage, 'bot-message');
                } else {
                    addMessage('Бот', data.message, 'bot-message');
                }
            })
            .catch(error => {
                addMessage('Бот', 'Произошла ошибка при проверке.', 'bot-message');
                console.error('Ошибка:', error);
            });

            // Очищаем поле ввода
            userInput.value = '';
        }

        // Обработчик кнопки "Отправить"
        sendButton.addEventListener('click', sendMessage);

        // Обработчик нажатия клавиши Enter
        userInput.addEventListener('keypress', (event) => {
            if (event.key === 'Enter') {
                sendMessage();
            }
        });
    </script>
</body>
</html>