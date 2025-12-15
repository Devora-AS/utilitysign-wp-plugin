import React from "react";
import ReactDOM from "react-dom/client";
import "./index.css";
import "../styles/devora-design-system.css";
import { RouterProvider } from "react-router-dom";
import { router } from "./routes";
import { ThemeProvider } from "@/components/theme-provider";
import { APIClientProvider } from "@/components/APIClientProvider";
const el = document.getElementById("utilitysign");

if (el) {
  // Map WP admin submenu slug to SPA route on boot
  try {
    const cfg = (window).utilitySign || {};
    const slug = cfg.currentPageSlug;
    const map = cfg.routeMap || {};
    const route = map[slug];
    if (route) {
      const targetHash = route.startsWith("#") ? route : `#${route.startsWith("/") ? route : `/${route}`}`;
      if (window.location.hash !== targetHash) {
        window.location.hash = targetHash;
      }
    }
  } catch (e) {
    // fail safe: ignore mapping errors
  }

  ReactDOM.createRoot(el).render(
    <ThemeProvider defaultTheme="light" storageKey="vite-ui-theme">
      <APIClientProvider>
        <React.StrictMode>
          <RouterProvider router={router} />
        </React.StrictMode>
      </APIClientProvider>
    </ThemeProvider>,
  );
}
