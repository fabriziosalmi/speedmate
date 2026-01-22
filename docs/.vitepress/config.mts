import { defineConfig } from 'vitepress'

export default defineConfig({
  title: "SpeedMate",
  description: "Free WordPress performance plugin with static cache and automation",
  base: '/speedmate/',
  ignoreDeadLinks: true,
  
  themeConfig: {
    logo: '/logo.svg',
    
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'API', link: '/api/rest-api' },
      { text: 'GitHub', link: 'https://github.com/fabriziosalmi/speedmate' }
    ],

    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'What is SpeedMate?', link: '/guide/what-is-speedmate' },
          { text: 'Getting Started', link: '/guide/getting-started' },
          { text: 'Installation', link: '/guide/installation' }
        ]
      },
      {
        text: 'Features',
        items: [
          { text: 'Static Cache', link: '/features/static-cache' },
          { text: 'Beast Mode', link: '/features/beast-mode' },
          { text: 'Media Optimization', link: '/features/media-optimization' },
          { text: 'Critical CSS', link: '/features/critical-css' },
          { text: 'Preload Hints', link: '/features/preload-hints' },
          { text: 'Traffic Warmer', link: '/features/traffic-warmer' }
        ]
      },
      {
        text: 'Configuration',
        items: [
          { text: 'Settings', link: '/config/settings' },
          { text: 'Beast Mode Rules', link: '/config/beast-mode' },
          { text: 'Cache Control', link: '/config/cache-control' },
          { text: 'Multisite', link: '/config/multisite' }
        ]
      },
      {
        text: 'API Reference',
        items: [
          { text: 'REST API', link: '/api/rest-api' },
          { text: 'WP-CLI Commands', link: '/api/wp-cli' },
          { text: 'Hooks & Filters', link: '/api/hooks' }
        ]
      },
      {
        text: 'Development',
        items: [
          { text: 'Architecture', link: '/dev/architecture' },
          { text: 'Testing', link: '/dev/testing' },
          { text: 'Contributing', link: '/dev/contributing' }
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/fabriziosalmi/speedmate' }
    ],

    footer: {
      message: 'Released under the GPL-3.0 License',
      copyright: 'Copyright © 2024-present Fabrizio Salmi'
    },

    search: {
      provider: 'local'
    }
  }
})
