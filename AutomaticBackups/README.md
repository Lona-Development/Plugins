# 🌸 Automatic Backups Plugin 🌸  

✨ **Effortlessly safeguard your LonaDB data with automatic backups!**  
This plugin creates regular backups of your database tables and ensures your data is secure and always restorable. Plus, a backup is automatically created every time the server starts. 💾  

---

## 🎀 Installation 🎀  

Setting up is as simple as 1-2-3:  

1. Download the `.phar` file from the **Releases** section.  
2. Place the file into the `plugins` folder of your **LonaDB** installation.  

And you’re all set — let the backups begin! 🚀  

---

## 🌷 Configuration 🌷  

Follow these steps to configure the plugin:  

1. **Create a Configuration Table**  
   - Add a table named `PluginConfiguration` to your database.  

2. **Set the Backup Interval**  
   - Add a variable named `AutomaticBackupsInterval` to the `PluginConfiguration` table.  
   - Possible values:  
     - `hourly` (every hour)  
     - `daily` (once a day)  
     - `weekly` (once a week)  
     - `none` (disable interval backups)  

3. **Restart your server** ✨  

---

## 🌸 How It Works 🌸  

- **Startup Backup:** A backup is automatically created each time the server starts, regardless of the configured interval.  
- **Scheduled Backups:** Based on the configured interval, additional backups are created regularly.  
- Backups are stored in a folder called `backups`, saved in JSON format, and include all table data.  

💡 **Backup naming format:**  
`<table_name>_<date-time>.json`  
(e.g., `users_20241125-1430.json`)  

---

## 💖 Why Choose Automatic Backups?  

- 🛡️ **Data Security**: Never lose your data.  
- ⚡ **Flexible Scheduling**: Choose between hourly, daily, or weekly backups—or just at startup.  
- 🌟 **Hassle-Free Setup**: Simple configuration for peace of mind. 