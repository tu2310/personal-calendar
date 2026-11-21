// Hash-based client-side router

export class Router {
  constructor(routes = {}) {
    this.routes = routes;
    this.currentRoute = null;
    this.init();
  }

  init() {
    window.addEventListener('hashchange', () => this.handleRouting());
    // Initial routing on page load
    this.handleRouting();
  }

  register(path, handler) {
    this.routes[path] = handler;
  }

  handleRouting() {
    let hash = window.location.hash || '#/hom-nay';
    if (!hash.startsWith('#/')) {
      hash = '#/hom-nay';
    }

    // Parse base route and params
    const [pathPart, queryPart] = hash.split('?');
    const handler = this.routes[pathPart] || this.routes['#/hom-nay'];

    this.currentRoute = pathPart;

    // Update active nav links in sidebar and bottom navigation
    document.querySelectorAll('.nav-link, .bottom-nav-item').forEach(link => {
      const href = link.getAttribute('href');
      if (href === pathPart) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      } else {
        link.classList.remove('active');
        link.removeAttribute('aria-current');
      }
    });

    if (handler) {
      handler(queryPart);
    }
  }

  navigate(path) {
    window.location.hash = path;
  }
}
