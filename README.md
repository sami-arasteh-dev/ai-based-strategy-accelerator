# Toulouka Strategy Accelerator

**AI-Powered Sales Strategy & Market Intelligence Dashboard for Wood & Metal Industries**

This project is a specialized web application designed for **Toulouka Wood and Metal Industries**. It leverages Large Language Models (LLMs) to analyze global and local news feeds, extract relevant market trends, and generate strategic sales ideas tailored to the company's specific product lines (furniture, home decor, metal products).

## 📋 Table of Contents
- [Features](#features)
- [Architecture & Tech Stack](#architecture--tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [File Structure](#file-structure)
- [Technical Notes](#technical-notes)
- [License](#license)

## ✨ Features

### 1. Semantic RSS Feed Analysis
- Connects to global news sources (Reuters, Investing.com, ISNA, etc.).
- Uses AI to filter news based on semantic relevance to user-defined keywords (e.g., "office furniture," "wood imports").
- **Image Extraction:** Automatically attempts to extract and convert news images to Base64 for inline display in the dashboard.

### 2. Strategic Idea Generation
- **Idea Analysis:** Converts raw user input into structured strategic cards.
- **Smart Suggestions:** Generates new sales strategies based on current market trends and company context.
- **Structured Output:** Returns JSON data containing strategy titles, categories, global benchmarks, implementation steps, and predicted impact.

### 3. Context-Aware AI
- Maintains a "Base Context" database (`data.json`) where users can store company-specific information (ERP data, website info, internal notes).
- AI models reference this context to ensure all generated strategies are relevant to Toulouka's actual business operations.

### 4. User-Friendly Interface
- **Dark Mode UI:** Professional, Linux-inspired dark theme for reduced eye strain.
- **Voice Input:** Built-in speech-to-text support for Persian (Farsi) to dictate ideas quickly.
- **History Management:** Save and review past strategic cards.

## 🏗️ Architecture & Tech Stack

The application follows a **Single-Page Application (SPA)** architecture with a lightweight PHP backend.

- **Frontend:**
  - HTML5 / CSS3 (Custom CSS variables for theming).
  - Vanilla JavaScript (ES6+) for DOM manipulation and API calls.
  - Web Speech API for voice input.
- **Backend:**
  - **PHP 8+:** Handles POST requests, processes AI API calls via cURL, and manages file-based data storage.
- **AI Integration:**
  - **GapGPT API:** Uses the `chat/completions` endpoint.
  - **Model:** Default configuration uses `gapgpt-qwen-3.5`.
- **Data Persistence:**
  - `data.json`: Stores base contexts and settings.
  - `history.json`: Stores generated strategic cards.

## 📦 Prerequisites

- A web server with **PHP 8.0+** support (Apache, Nginx, or local development server like XAMPP/MAMP).
- **cURL** extension enabled in PHP.
- An active **API Key** from a compatible LLM provider (configured in `config.json`).
- Internet connection for RSS feed fetching and AI API calls.

## 🚀 Installation

1.  **Clone or Download:**
    Place the `10.php` file and `config.json` in your web server's document root (e.g., `/var/www/html/` or `htdocs/`).

2.  **Set Permissions:**
    Ensure the web server user has write permissions to the directory where `data.json` and `history.json` will be created.
    ```bash
    chmod 755 /path/to/project
    ```

3.  **Access the Application:**
    Navigate to `http://localhost/10.php` (or your configured domain).

## ⚙️ Configuration

### API Configuration (`config.json`)
The application uses a JSON configuration file for API credentials.

```json
{
  "api_key": "sk-YOUR_API_KEY_HERE",
  "base_url": "https://api.gapgpt.app/v1",
  "model_name": "gapgpt-qwen-3.5"
}
```

*Note: The `10.php` file also contains a hardcoded config object in the JavaScript section for immediate use. For production, ensure the API key in `config.json` is secure and not exposed in client-side code if possible.*

### RSS Feeds
The application comes with pre-configured RSS feed URLs in the HTML dropdown:
- Reuters Business
- Investing.com Commodities
- ISNA (Economy)
- Tejarat News

You can add custom RSS URLs via the "Custom" option in the UI.

## 🖱️ Usage

### 1. Analyzing Ideas
1.  Enter your **API Key** in the settings panel.
2.  Type a raw idea in the "Raw Idea" textarea (or use the microphone icon).
3.  Click **"Analyze Idea"**. The AI will generate a strategic card with implementation steps and benchmarks.

### 2. Generating Smart Suggestions
1.  Select relevant RSS feeds from the "Strategic Observatory" section.
2.  Optionally, add keywords to filter the news.
3.  Click **"Generate Smart Idea"**. The AI will combine current news trends with your company context to propose new sales strategies.

### 3. Managing Base Context
1.  Go to the **"Data"** tab.
2.  Add titles and descriptions of your company's internal data (e.g., "ERP Data: Q3 Sales Drop in Metal Products").
3.  Click **"Save Base Card"**. This data is used to ground all AI responses.

### 4. Viewing History
1.  Go to the **"History"** tab.
2.  Review previously saved strategic cards.
3.  Click **"Save to History"** on any generated card to archive it.

## 📂 File Structure

```text
/
├── 10.php              # Main application file (UI + Backend Logic)
├── config.json         # API configuration (Key, URL, Model)
├── data.json           # Auto-generated: Stores base contexts and settings
├── history.json        # Auto-generated: Stores saved strategic cards
└── uploads/            # Auto-generated: Directory for potential file uploads (currently unused)
```

## 🔧 Technical Notes

- **JSON Output Enforcement:** The system prompts the LLM to return strict JSON objects. The frontend parses this JSON to render the UI components dynamically.
- **Base64 Image Handling:** The AI is instructed to convert image URLs to Base64 strings. The frontend handles the `data:image/jpeg;base64,...` format for direct display in `<img>` tags.
- **Error Handling:** The PHP backend silences errors (`error_reporting(0)`) for a cleaner production environment but returns JSON error messages to the frontend for debugging.
- **Security:**
  - The API key is passed via POST requests.
  - Input validation is performed on the server side (checking for empty strings).
  - File writes are restricted to the application directory.

## 🤝 Development

To extend the application:
1.  **Add New Feeds:** Modify the `<select>` element in `10.php` to include new RSS URLs.
2.  **Customize Prompts:** Edit the `$systemPrompt` variables in the PHP section to change the AI's behavior or persona.
3.  **UI Customization:** Modify the CSS variables in the `<style>` block to change colors and fonts.

## 📄 License

This project is proprietary software designed for **Toulouka Wood and Metal Industries**. Internal use only.
