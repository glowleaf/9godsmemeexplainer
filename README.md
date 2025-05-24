# 9 Gods Meme Explainer WordPress Plugin

A WordPress plugin that uses GPT-4 Vision to automatically explain memes and funny images through the adorable persona of Gorgocutie, a friendly Medusa! 🐍✨

## Features

- **Automatic Processing**: Processes featured images from blog posts using GPT-4 Vision API
- **Batch Processing**: Runs via WordPress cron every hour to process posts in small batches
- **Status Tracking**: Admin interface shows processing status for all posts
- **Retry Functionality**: Easy retry options for failed processing attempts
- **Custom Avatar**: Upload Gorgocutie's avatar image for personalized explanations
- **Frontend Display**: Automatically appends explanations to single post views
- **Error Handling**: Comprehensive error logging and status tracking

## Installation

1. **Upload Plugin Files**
   ```
   /wp-content/plugins/9gods-meme-explainer/
   ├── 9gods-meme-explainer.php
   └── includes/
       ├── admin.php
       ├── cron.php
       └── gpt.php
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "9 Gods Meme Explainer" and click "Activate"

3. **Configure Settings**
   - Go to Settings → 9 Gods Meme Explainer
   - Add your OpenAI API key
   - Upload Gorgocutie's avatar image (optional)

## Configuration

### OpenAI API Key
1. Get an API key from [OpenAI](https://platform.openai.com/api-keys)
2. Ensure you have access to GPT-4 Vision (`gpt-4-vision-preview`)
3. Add the key in the plugin settings

### Gorgocutie