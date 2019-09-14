# Description

You can create multiple Atom & JSON feeds

# Installation

1. Download and unzip.
2. Copy `Feed` folder to `yoursite/site/addons`.

# Configuration 
1. Visit `yoursite.com/cp/addons/feed/settings` or `CP > Configure > Addons > Feed`.
2. If you don't have individual entry authors, set one here (name/dept/company)
3. Add any discovery sites you'd like to use (SuprFeedr, etc)
4. Add a feed
5. Set the feed's route, type (Atom or JSON) & title 
6. Choose the collection(s) used for the feed along with the field used to reference the author
7. Name fields are the fields within your author data. Put these in the appropriate order (first before last, etc).
8. Toggle `custom_content` to use a partial to render the data in your entry. Otherwise, the entry's `content` field will be used.

# Usage

Just use the above URLs whenever needed (like in the `<head>` section of your layout) for your feeds