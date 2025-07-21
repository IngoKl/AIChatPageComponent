# Project Overview

This project (AIChatPageComponent) is an ILIAS plugin (PageComponent) that serves the purpose of allowing users to include individual AI chats within ILIAS pages. These individual chats have specific settings, most importantly, a custom system prompt. They are used, for example, for educational examples. For example, users learn about generative AI and then, on the same page, they can interact with a chatbot that has been set up, via the system prompt, for the specific exercise.

This plugin is very closely related to the AIChat plugin, which needs to be installed as well. The AIChat plugin usually resides in `/var/www/html/Customizing/global/plugins/Services/Repository/RepositoryObject/AIChat`. AIChatPageComponent always relies on AIChat for its basic configuration, etc. and shares it with AIChat. In this relationship, AIChat is the leading plugin, providing, for example, default settings. AIChatPageComponent should, wherever possible, tap into the available data, e.g., configurations, from AIChat.

# Stack

- ILIAS 9
- PHP 8.2
- MySQL 8

# Possible Examples and Best Practices

ILIAS code is not prominently available. Consider these repos for best practices:

- Simple RepositoryObject Example: https://github.com/srsolutionsag/TestRepositoryObject
- Complex RepositoryObject EXample https://github.com/surlabs/WhiteboardForILIAS
- Simple PageComponent Example: https://github.com/surlabs/TestPageComponent
- Complex PageComponent Example: https://github.com/srsolutionsag/H5PPageComponent

# Additional Best Practices

- Follow existing patterns and don't create custom solutions
- Separate functionality and style (e.g., CSS) as much as possible