# WP Publish with Buffer

![WordPress](https://img.shields.io/badge/WordPress-%23117B85.svg?style=for-the-badge&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![Buffer API](https://img.shields.io/badge/Buffer_API-black?style=for-the-badge&logo=buffer&logoColor=white)

A WordPress plugin that automates the publishing of posts to your social media channels using the Buffer API. 

## 🚀 Features

* **Automatic Publishing:** Automatically sends your WordPress posts to Buffer the moment they are published.
* **Scheduled Posts Support:** Works seamlessly with native WordPress scheduled posts (`publish_future_post`).
* **Multiple Publishing Modes:**
    * **Share Now:** Publishes immediately.
    * **Add to Queue:** Adds the post to your Buffer queue according to your predefined schedule.
    * **Custom Scheduled:** Allows you to pick a specific date and time for the Buffer post.
* **Customizable Message Templates:** Create your own message format using dynamic variables:
    * `{title}`: Post title.
    * `{url}`: Post permalink.
    * `{excerpt}`: Post excerpt.
    * `{author}`: Author's display name.
    * `{category}`: Primary category of the post.
    * `{tags}`: Post tags (automatically converted to #hashtags).
* **Category Filtering:** Restrict automatic Buffer posting only to posts belonging to a specific category.
* **Featured Image Support:** Automatically attaches the post's featured image to the Buffer publication.
* **Duplicate Prevention:** Internally tracks when a post has already been shared to Buffer to avoid duplicate submissions upon post updates.
* **Translation Ready (i18n):** Fully prepared for translation. English and Spanish (`es_ES`) languages are included by default.

## 📦 Installation

1. Download the latest version of the plugin as a `.zip` file from this repository (Click on **Code > Download ZIP**).
2. Go to your WordPress admin dashboard, navigate to **Plugins > Add New**, and click on **Upload Plugin**.
3. Select the downloaded `.zip` file and click **Install Now**.
4. Once installed, click **Activate**.

*Alternatively, you can extract the `.zip` file and upload the `wp-publish-with-buffer` folder directly to your `/wp-content/plugins/` directory via FTP.*

## ⚙️ Configuration and Usage

### 1. Settings Panel
Once the plugin is activated, go to **Settings > Publish with Buffer** in your WordPress admin dashboard.

You will need to configure the following:
* **Buffer API Token:** Your personal Buffer API access token.
* **Channel IDs:** The IDs of the Buffer channels where you want to publish (comma-separated, e.g. `id_1, id_2`).
* **Message Template:** The structure of the message for your social media posts.
* **Default Publishing Mode:** The default mode selected in the post editor.
* **Filter by Category (Optional):** Select a specific category if you only want posts within that topic to be published.

### 2. Usage in the Post Editor
On the edit screen of any post, you will see a new **"Publish with Buffer"** meta box in the sidebar.

* Check the **"Send to Buffer on publish"** box to enable sending for that specific post.
* Select your preferred publishing mode.
* When the post is published (or whenever it is automatically published if scheduled), the plugin will send the data to Buffer via their GraphQL API.
* Once a post is successfully sent, the panel will display a success notice and hide the publishing options to prevent duplicate posts.

## 🛠️ Technologies Used

* **WordPress Plugin API:** Hooks, Settings API, Meta Boxes.
* **PHP:** Backend logic and HTTP requests (`wp_remote_post`).
* **GraphQL / Buffer API:** Integrates with Buffer to asynchronously create and schedule posts.