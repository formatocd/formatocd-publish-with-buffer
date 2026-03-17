=== Publish with Buffer ===
Contributors: formatocd
Tags: buffer, social media, auto publish, schedule, graphql
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates Buffer posts automatically from WordPress posts. Connect your site to the Buffer GraphQL API.

== Description ==

Publish with Buffer is a powerful, lightweight plugin that automates the publishing of your WordPress posts to your social media channels using the modern Buffer GraphQL API. 

Stop copying and pasting links! With this plugin, you can easily share your content across multiple social networks as soon as you hit "Publish", or schedule them to be added to your Buffer queue.

= 🚀 Features =

* **Automatic Publishing:** Automatically sends your WordPress posts to Buffer the moment they are published.
* **Scheduled Posts Support:** Works seamlessly with native WordPress scheduled posts (`publish_future_post`).
* **Multiple Publishing Modes:** Choose between Share Now, Add to Queue, or Custom Scheduled.
* **Customizable Message Templates:** Create your own message format using dynamic variables like `{title}`, `{url}`, `{author}`, and more.
* **Smart Hashtags:** Automatically converts your WordPress tags into #hashtags for social media.
* **Category Filtering:** Restrict automatic Buffer posting only to posts belonging to a specific category.
* **Featured Image Support:** Automatically attaches the post's featured image to the Buffer publication.
* **Duplicate Prevention:** Internally tracks when a post has already been shared to Buffer to avoid duplicate submissions upon post updates.
* **Translation Ready (i18n):** Fully prepared for translation. English and Spanish (`es_ES`) languages are included by default.

== Installation ==

1. Upload the `publish-with-buffer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Settings > Publish with Buffer** to configure your API Token and Channels.
4. Go to any Post and look for the "Publish with Buffer" meta box in the sidebar to start sharing.

== External Services ==

This plugin connects to the third-party Buffer GraphQL API (https://api.buffer.com) to automatically publish or schedule your social media posts.

Data is sent to Buffer ONLY when a WordPress post is published (or automatically published via WordPress cron) and the user has actively checked the "Send to Buffer on publish" option.

The specific data sent to the Buffer API includes:
* Post Title
* Post URL
* Post Excerpt
* Author Display Name
* Post Categories and Tags (formatted as #hashtags)
* Featured Image URL

This service is provided by Buffer. By using this plugin, you agree to their legal terms:
* Buffer Terms of Service: https://buffer.com/legal/terms
* Buffer Privacy Policy: https://buffer.com/legal/privacy

API Documentacion can be found here: https://developers.buffer.com

== Frequently Asked Questions ==

= Where do I get my Buffer API Token? =
You need to create a custom app in the Buffer Developer Portal to generate your personal access token. Note that this uses the new Buffer GraphQL API.

= How do I find my Channel IDs? =
You can find your Channel IDs by querying the Buffer GraphQL API or by looking at the URL in your Buffer dashboard when viewing a specific channel.

= Does it support scheduled posts? =
Yes! If you schedule a post in WordPress to be published next week, the plugin will wait and automatically send it to Buffer at the exact moment WordPress publishes it.

== Screenshots ==

1. The Global Settings Panel where you configure your Buffer API and message templates.
2. The Meta Box inside the Post Editor to control the publishing mode per post.
3. Success message after a post has been successfully sent to Buffer.

== Changelog ==

= 1.0.0 =
* Initial public release on the WordPress repository.
* Added support for GraphQL Buffer API.
* Included dynamic variables and UI improvements to prevent duplicate posts.