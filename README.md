# Fork Notice

This repository is a fork of the original FreeScout ChatGPT Integration Module. I have decided to continue development here since the original plugin has been inactive for some time. The goal is to implement new features and maintain compatibility with current FreeScout versions while keeping the core functionality intact.

Key improvements planned in this fork:
- Multiple prompt support
- Grammar checking capabilities  
- Multiple answer selection
- Compatibility updates for FreeScout v1.8.156
- Additional GPT model options
- Enhanced context management
- Historical conversation integration

The original work and credit goes to the initial developers. This fork aims to build upon their foundation to create an even more robust integration.


# FreeScout ChatGPT Integration Module (HostetskiGPT)

This repository contains the FreeScout ChatGPT Integration Module, which connects FreeScout with the powerful language model ChatGPT by OpenAI. This integration enables the generation of AI-based responses for incoming messages, providing a more efficient and intelligent support system for your helpdesk.

![FreeScout ChatGPT Integration Module Example](https://my.hostetski.com/files/img/hostetskigpt.jpg "Integration Module Example")

## Features
- Generate AI-based responses for each incoming message
- Support for multiple GPT models (GPT-3.5 Turbo, GPT-4, GPT-4 Turbo, GPT-4 Turbo Preview)
- Utilize the powerful ChatGPT language model to improve support efficiency
- Customizable starting message to set the AI's role (e.g., support agent, sales manager, etc.), associate it with your brand, or provide additional context

![FreeScout ChatGPT Integration Module Example](https://my.hostetski.com/files/git/gpt.gif "Integration Module Example")


## Requirements
To use this module, you will need an API key for ChatGPT, which can be obtained from the OpenAI platform at https://platform.openai.com/account/api-keys.

## Configuration
1. Install the FreeScout ChatGPT Integration Module
2. Add your ChatGPT API key to the module's configuration page.
3. Set a "prompts message" for the AI.

![FreeScout ChatGPT Integration Module Example](https://my.hostetski.com/files/git/gpt-settings.png "GPT Setting Page")

## TODO
 - [x] Settings via web interface
 - [x] Loader, which shows that the response is being generated
 - [ ] Multiple prompts
 - [ ] Grammar check
 - [ ] Select multiple answers in a conversation
 - [ ] Compatibility with FreeScout v1.8.156
 - [x] Add option for GPT-4 models
 - [ ] Additional field to set context for the AI
 - [ ] Option not only to send customer information, but also previous conversations
 - [X] Show a summary of the conversation at the top of a conversation
 - [ ] Option to disable summary generation

## Contributing
~~This is an early version of the FreeScout ChatGPT Integration Module, and we appreciate any feedback, suggestions, or contributions to help improve the module. Please feel free to open issues or submit pull requests on GitHub, or send your messages and suggestions to our email: [support@cloudcenter.ovh](mailto:support@cloudcenter.ovh).~~

Together, we can make this integration a valuable addition to the FreeScout ecosystem and enhance the capabilities of helpdesk software for the entire community.
