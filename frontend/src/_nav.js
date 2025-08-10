export default [
  {
    component: 'CNavItem',
    name: 'Dashboard',
    to: '/dashboard',
    icon: 'cil-speedometer',
    badge: {
      color: 'primary',
      text: 'NEW',
    },
  },
  {
    component: 'CNavTitle',
    name: 'Fitur Barru Bercerita',
  },

  {
    component: 'CNavItem',
    name: 'Widgets',
    to: '/widgets',
    icon: 'cil-calculator',
    badge: {
      color: 'primary',
      text: 'NEW',
      shape: 'pill',
    },
  },

  {
    component: 'CNavItem',
    name: 'Login',
    to: '/pages/login',
    icon: 'cil-user',
    badge: {
      color: 'secondary',
      text: 'NEW',
    },
  },

  {
    component: 'CNavItem',
    name: 'Colors',
    to: '/theme/colors',
    icon: 'cil-drop',
  },
  {
    component: 'CNavItem',
    name: 'AdminDashboard',
    to: '/admin/dashboard',
    icon: 'cil-speedometer',
  },
]
