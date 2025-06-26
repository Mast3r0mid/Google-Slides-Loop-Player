## Google Slides Loop Player(digital signage player)


### A Lightweight, Browser-Based Solution for Cycling Through Google Slides Presentations


------------



### What is This?

This project provides a simple, open-source PHP and JavaScript application designed to display a sequence of Google Slides presentations in a continuous loop. Think of it as a custom digital signage player specifically optimized for Google Slides content.

Instead of relying on proprietary digital signage platforms (like TrilbyTV) that might have complex setups, licensing costs, or specific hardware requirements, this solution runs directly in a web browser. 

------------


##### It's ideal for:

Displaying rotating announcements or information on a screen in a reception area, classroom, or office.
Creating a simple kiosk display.
Anyone who needs a straightforward way to cycle through multiple Google Slides presentations without manual intervention.


------------



##### How Does it Work?
You provide a list of Google Slides "published to the web" URLs, and the application does the rest:

It loads the first presentation.
Crucially, it intelligently calculates the total duration required for each presentation based on the number of slides within it (assuming a standard delay per slide as defined in your Google Slides publish settings).
Once the calculated duration for a presentation expires, it automatically loads the next one in your list.
When it reaches the end of the list, it loops back to the beginning, creating a continuous display.


------------


##### Key Features

- Google Slides Focused: Specifically designed to play publicly shared Google Slides presentations.
- Automatic Looping: Seamlessly cycles through a predefined list of presentations.
- Browser-Based: Runs entirely within any modern web browser (Chrome, Firefox, Edge, etc.) – no special software installation or server-side components needed.
- Lightweight & Simple: Minimalistic design and code, making it easy to understand, modify, and deploy.
- No Licensing Costs: Completely free and open-source.
- Cross-Platform: Works on any device with a modern web browser (Windows, macOS, Linux, Raspberry Pi, Android, smart TVs with web browsers).

##### Why Use This Instead of Other Signage Apps (like TrilbyTV)?
- Cost-Effective: Zero software costs. Leverage your existing Google Slides content without additional subscriptions.
- Simplicity: No complex dashboard, content management system, or network setup to configure. Just edit a single HTML file.
- Direct Google Slides Integration: Optimized for how Google Slides publishes presentations, including loop and delay settings.

- Resource Friendly: A lightweight HTML/JavaScript page consumes minimal system resources, making it suitable for less powerful devices (e.g., Raspberry Pi for digital signage).
- Customization: Easily modify the HTML, CSS, and JavaScript to fit your exact branding or functionality needs.



------------


### Getting Started
1.  Download the ZIP file directly from the GitHub repository page.

2. Configure Your Google Slides URLs :
- Publish Your Google Slides: For each Google Slides presentation you want to use, open it in Google Slides. Go to File > Share > Publish to web.

- Select the "Embed" tab.
- Choose your desired Auto-advance slides time (e.g., "Every 5 seconds as Default"). This is the delayms value in your URL.
- Check "Start slideshow as soon as the player loads" (start=true).
- Check "Restart slideshow after the last slide" (loop=true).
- Copy the src URL from the generated "< iframe >" code. It will look something like: `https://docs.google.com/presentation/d/e/2PACX-1v.../pubembed?start=true&loop=true&delayms=5000`
- Edit Presentaion list:
  
  i-  if you are useing python simple HTTP server:

  Edit Player.html: Open the Player.html file in a text editor. Locate the presentations array within the "< script >" tags:

```javascript
JavaScript

const presentations = [
    {
        // presentation 1
        url: "YOUR_FIRST_GOOGLE_SLIDES_PUBLISHED_URL_HERE",
        duration: 5001, // Must be 1ms more than your Google Slides 'delayms' value (e.g., 5000 + 1 = 5001) This slight offset ensures the script waits for the slide transition to complete before counting slides.
        originalDuration: 5001 // Same as duration
    },
    {
        // presentation 2
        url: "YOUR_SECOND_GOOGLE_SLIDES_PUBLISHED_URL_HERE",
        duration: 5001,
        originalDuration: 5001
    }
    // Add more presentations by copying and pasting the structure above
];
```

 ii- If you are using php server :
 
 Go to Login page in a WebBrowser. Edit the presentations list

3. Run the Application
- Simply open the Player.html file in your web browser.

#### Important Note for Local File Access:
Modern browsers have security restrictions that can limit JavaScript's ability to fetch content from file:// URLs (CORS policy) .  If you experience issues with the automatic slide counting or transitions, it's recommended to run the application using a simple local web server.

#### Running with a Local Web Server
This ensures all features work correctly and simulates a real web environment.

 - Using Python's Simple HTTP Server (if Python is installed)
1. Open your terminal or command prompt.
2. Navigate to your project directory (where Player.html is located).
3. Run the command:
`
python -m http.server 8080
`
-  For Python 2:
 `python -m SimpleHTTPServer 8080`

 - Using PHP server (Recommended)
   
`
php.exe -S 127.0.0.1:8080 -t ROOT_DIRECTORY
`
- Open your browser and go to the address provided (usually http://127.0.0.1:8080 or http://localhost:8080).




------------

------------



#### How the Slide Counting Works (Technical Detail)
The script fetches the HTML content of the published Google Slides URL. It then searches for a specific JavaScript variable that Google includes in its published slide content: SK_modelChunkParseStart. The number of times this variable appears indicates the number of "slides" or "chunks" Google Slides prepares. This count is then multiplied by the duration (which includes your delayms) to determine the total display time for that specific presentation before moving to the next one. This ensures that even if you have many slides, the player waits for them all to cycle through.


------------


------------


#### Open an issue for bugs or suggestions.

------------


------------

### License
This project is open source and available under the [MIT License](https://github.com/Mast3r0mid/Google-Slides-Loop-Player/blob/main/LICENSE). 

------------


------------

