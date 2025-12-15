import {
  CircleUser,
  Home,
  SlidersHorizontal,
  Menu,
} from "lucide-react";
import { useMemo } from "react";
import { Button } from "@/components/ui/button";
import { ModeToggle } from "../mode-toggle";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { NavLink, Outlet, useLocation } from "react-router-dom";
import Logo from "../Icons/Logo";
import { clsx } from "clsx";

const navigation = [
  { name: "Dashboard", href: "/", icon: Home },
  { name: "Settings", href: "/settings", icon: SlidersHorizontal },
];

export default function LayoutOne() {
  const cfg = typeof window !== "undefined" ? (window).utilitySign || {} : {};
  const routeMap = cfg.routeMap || {};
  const isAdmin = cfg.isAdmin ?? true;
  const location = useLocation();

  const currentPath = location.pathname === "/" ? "/" : location.pathname.replace(/^\/+/, "/");

  const navItems = useMemo(() => {
    return navigation.map((item) => {
      const slug = Object.keys(routeMap).find((key) => {
        const route = routeMap[key];
        const normalized = route.startsWith("/") ? route : `/${route}`;
        return normalized === item.href;
      });

      return {
        ...item,
        slug,
      };
    });
  }, [routeMap]);

  return (
    <div className={`grid min-h-screen w-full ${
      !isAdmin ? "" : "md:grid-cols-[220px_1fr] lg:grid-cols-[280px_1fr]"
    }`}>
      {isAdmin && (
        <div className="hidden border-r bg-muted/40 md:block">
          <div className="flex h-full max-h-screen flex-col gap-2">
            <div className="flex h-14 items-center border-b px-4 lg:h-[60px] lg:px-6">
              <a href="#/dashboard" className="flex items-center gap-2 font-semibold">
                <Logo />
                <span className="">UtilitySign</span>
              </a>
            </div>
            <div className="flex-1">
              <nav className="grid items-start px-2 text-sm font-medium lg:px-4">
                {navItems.map((item) => (
                  <NavLink
                    to={item.href}
                    key={item.href}
                    className={({ isActive }) =>
                      clsx(
                        "flex items-center gap-3 rounded-lg px-3 py-2 transition-all hover:text-primary",
                        isActive || currentPath === item.href
                          ? "text-primary bg-muted"
                          : "text-muted-foreground"
                      )
                    }
                  >
                    <item.icon className="h-5 w-5" />
                    {item.name}
                  </NavLink>
                ))}
              </nav>
            </div>
          </div>
        </div>
      )}
      <div className="flex flex-col">
        {isAdmin && (
          <header className="flex h-14 items-center gap-4 border-b bg-muted/40 px-4 lg:h-[60px] lg:px-6">
            <Sheet>
              <SheetTrigger asChild>
                <Button variant="outline" size="icon" className="shrink-0 md:hidden">
                  <Menu className="h-5 w-5" />
                  <span className="sr-only">Toggle navigation menu</span>
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="flex flex-col">
                <nav className="grid gap-2 text-lg font-medium">
                  {navItems.map((item) => (
                    <NavLink
                      to={item.href}
                      key={item.href}
                      className={({ isActive }) =>
                        clsx(
                          "flex items-center gap-3 rounded-lg px-3 py-2 transition-all hover:text-primary",
                          isActive || currentPath === item.href
                            ? "text-primary bg-muted"
                            : "text-muted-foreground"
                        )
                      }
                    >
                      <item.icon className="h-5 w-5" />
                      {item.name}
                    </NavLink>
                  ))}
                </nav>
              </SheetContent>
            </Sheet>
            <div className="flex-1" />
            <ModeToggle />
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="secondary" size="icon" className="rounded-full">
                  <CircleUser className="h-5 w-5" />
                  <span className="sr-only">Toggle user menu</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuLabel>My Account</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem>Settings</DropdownMenuItem>
                <DropdownMenuItem>Support</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem>Logout</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </header>
        )}
        <main>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
