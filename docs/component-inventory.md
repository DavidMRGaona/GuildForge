# Component inventory

> Generated: 2026-03-21 | Project: GuildForge

---

## 1. Pages (Inertia)

All pages are located in `src/resources/js/pages/`.

### Authentication

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Auth/Login.vue` | `/iniciar-sesion` | -- (uses `useForm`) | `useSeo`, `useAuth`, `useNotifications`, `useRoutes` | Login form with email/password, "remember" checkbox, conditional link to registration (`authSettings.registrationEnabled`) |
| `Auth/Register.vue` | `/registro` | -- (uses `useForm`) | `useSeo`, `useAuth`, `useNotifications`, `useRoutes` | Registration form with name, email, password and confirmation. Conditional link to login (`authSettings.loginEnabled`) |
| `Auth/ForgotPassword.vue` | `/olvide-contrasena` | -- (flash messages via `usePage`) | `useSeo` | Password recovery form by email. Shows success message after submission |
| `Auth/ResetPassword.vue` | `/restablecer-contrasena` | `token: string`, `email: string` | `useSeo` | Password reset form with token, email, new password and confirmation |
| `Auth/VerifyEmail.vue` | `/verificar-email` | -- (flash messages via `usePage`) | `useSeo` | Email verification page with button to resend email |

### Events

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Events/Index.vue` | `/eventos` | `events: PaginatedResponse<Event>`, `tags: Tag[]`, `currentTags: string[]` | `useSeo`, `usePagination`, `useRoutes` | Paginated event listing with tag filter (`TagFilter`). Pagination with previous/next buttons |
| `Events/Show.vue` | `/eventos/:slug` | `event: Event` | `useEvents`, `useTags`, `useSeo`, `useRoutes` | Event detail with hero image, status badges (upcoming/past), tags, date range, location, prices, download links, module slot (`event-detail-actions`), HTML description |

### Articles

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Articles/Index.vue` | `/articulos` | `articles: PaginatedResponse<Article>`, `tags: Tag[]`, `currentTags: string[]` | `useSeo`, `usePagination`, `useRoutes` | Paginated article listing with tag filter |
| `Articles/Show.vue` | `/articulos/:slug` | `article: Article` | `useArticles`, `useTags`, `useSeo`, `useRoutes` | Article detail with hero image, author avatar, publication date, tags, HTML content |

### Gallery

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Gallery/Index.vue` | `/galeria` | `galleries: PaginatedResponse<Gallery>`, `tags: Tag[]`, `currentTags: string[]` | `useSeo`, `usePagination`, `useRoutes` | Paginated gallery listing with tag filter |
| `Gallery/Show.vue` | `/galeria/:slug` | `gallery: Gallery` | `useLightbox`, `useGallery`, `useTags`, `useSeo`, `useRoutes` | Gallery detail with photo grid, tags, photo count, lightbox for full-screen viewing. `PhotoLightbox` loaded as `defineAsyncComponent` |

### Calendar

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Calendar/Index.vue` | `/calendario` | -- | `useSeo`, `useMediaQuery` | Full calendar page. On desktop: calendar (2/3) + side detail panel (1/3). On mobile: calendar + bottom modal for detail. Automatic selection of next event |

### Profile

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Profile/Show.vue` | `/perfil` | `user: User` | `useSeo`, `useProfileTabs`, `useModuleSlots`, `useFlashMessages` | User profile with header, tab bar (mobile) / sidebar (desktop), account tab, dynamic module tabs via `profile-sections` slot. Success/error flash messages |

### Search

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Search/Index.vue` | `/buscar` | `query: string`, `events: Event[]`, `articles: Article[]`, `error?: string \| null` | -- | Global search with debounced input, results grouped by section (events and articles), empty states, minimum character warning. Meta `noindex,nofollow` |

### Legal

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Legal/Show.vue` | (dynamic) | `title: string`, `content: string`, `lastUpdated: string \| null` | `useSeo` | Renders legal pages (legal notice, privacy, cookies) with HTML content and last updated date |

### Other

| Page | Route | Props | Composables | Features |
|------|-------|-------|-------------|----------|
| `Home.vue` | `/` | `heroSlides: HeroSlide[]`, `upcomingEvents: Event[]`, `latestArticles: Article[]`, `featuredGallery: Gallery \| null` | `useSeo`, `useLightbox`, `useRoutes` | Home page with hero slider, events section (grid + compact calendar), recent articles, featured gallery in asymmetric mosaic with lightbox |
| `About.vue` | `/nosotros` | `guildName: string`, `aboutHistory: string`, `contactEmail/Phone/Address: string`, `aboutHeroImage: string`, `aboutTagline: string`, `activities: Activity[]`, `joinSteps: JoinStep[]`, `social*: string`, `location: object \| null` | `useSeo` | "About us" page with hero image, association activities, history (HTML), steps to join (horizontal/vertical timeline), contact (email, phone, address, social media), contact form, Leaflet map |

---

## 2. Layouts

Located in `src/resources/js/layouts/`.

### DefaultLayout

- **File**: `DefaultLayout.vue`
- **Slots**: `default` (main content)
- **Shared functionality**:
  - Injects dynamic theme CSS variables
  - Loads web fonts from Bunny Fonts
  - "Skip to content" link for accessibility
  - Initializes and observes theme changes (`useAppStore`)
  - Manages dynamic favicons (`useFavicons`)
  - Module slots: `before-header`, `after-header`, `before-content`, `after-content`, `before-footer`, `after-footer`
  - Includes `TheHeader`, `TheFooter`, `NotificationToast`

### AuthLayout

- **File**: `AuthLayout.vue`
- **Props**: `title: string`, `subtitle?: string`
- **Slots**: `default` (form)
- **Shared functionality**:
  - Same theme and font injection as `DefaultLayout`
  - Vertical centering, link to app logo/name
  - "Back to home" link
  - No header/footer (minimalist auth design)

---

## 3. Components

Located in `src/resources/js/components/`.

### Layout

#### TheHeader

- **File**: `layout/TheHeader.vue`
- **Props**: --
- **Emits**: --
- **Features**: Logo with light/dark support, desktop navigation (`TheNavigation`), search button, theme selector (system/light/dark) with dropdown, `UserDropdown` or `AuthLinks` based on authentication, mobile hamburger menu with the same options

#### TheFooter

- **File**: `layout/TheFooter.vue`
- **Props**: --
- **Emits**: --
- **Features**: Footer navigation links (configurable from backend, with fallback), copyright with dynamic year, "made with" text, social media links via `SocialLinks`

#### TheNavigation

- **File**: `layout/TheNavigation.vue`
- **Props**: `mobile?: boolean` (default: `false`)
- **Emits**: `navigate`
- **Features**: Renders backend menu items (Inertia shared props `navigation.header`) with fallback to default links. Dropdown support (`NavDropdown`) for items with children. Marks active item based on current URL. Horizontal (desktop) or vertical (mobile) layout

#### NavDropdown

- **File**: `layout/NavDropdown.vue`
- **Props**: `item: MenuItem`, `mobile?: boolean`
- **Emits**: `navigate`
- **Features**: Navigation dropdown for items with children

#### AuthLinks

- **File**: `layout/AuthLinks.vue`
- **Props**: `mobile?: boolean`
- **Emits**: `navigate`
- **Features**: Login/registration links for unauthenticated users

#### UserDropdown

- **File**: `layout/UserDropdown.vue`
- **Props**: --
- **Emits**: `navigate`
- **Features**: Authenticated user dropdown with avatar, name, links to profile, admin panel (if authorized), logout

#### ModuleSlot

- **File**: `layout/ModuleSlot.vue`
- **Props**: `name: SlotPosition`
- **Emits**: --
- **Features**: Extension point for modules. Resolves and renders module components registered for the given position. Uses `Suspense` for async loading. Captures errors per individual component without affecting the rest. Shows errors in development mode

### UI

#### BaseButton

- **File**: `ui/BaseButton.vue`
- **Props**: `variant?: 'primary' | 'secondary' | 'danger' | 'ghost'`, `size?: 'sm' | 'md' | 'lg'`, `disabled?: boolean`, `loading?: boolean`, `type?: 'button' | 'submit' | 'reset'`
- **Emits**: `click`
- **Features**: Reusable button with visual variants, sizes, loading state with built-in spinner (`LoadingSpinner`), minimum 44px for touch accessibility

#### BaseCard

- **File**: `ui/BaseCard.vue`
- **Props**: `title?: string`, `subtitle?: string`, `padding?: boolean` (default: `true`)
- **Slots**: `default`, `header`, `footer`
- **Features**: Card with shadow, rounded border, optional header with title/subtitle, optional footer with muted background

#### LoadingSpinner

- **File**: `ui/LoadingSpinner.vue`
- **Props**: `size?: 'sm' | 'md' | 'lg'`, `color?: string` (default: `'text-primary'`)
- **Features**: Animated SVG spinner with `role="status"` and hidden text for screen readers

#### TagBadge

- **File**: `ui/TagBadge.vue`
- **Props**: `tag: Tag`, `linkable?: boolean`, `size?: 'sm' | 'md'`, `badgeStyle?: 'solid' | 'subtle' | 'overlay'`, `variant?: 'category' | 'tag'`, `contentType?: 'events' | 'articles' | 'galleries'`
- **Features**: Tag badge with dynamic color. "Subtle" style calculates opacity and contrast based on light/dark mode. "Overlay" style for use on images with `backdrop-filter`. If `linkable`, links to the section filtered by that tag

#### TagList

- **File**: `ui/TagList.vue`
- **Props**: `tags: Tag[]`, `maxVisible?: number` (default: 3), `linkable?: boolean`, `size?: 'sm' | 'md'`, `variant?: 'category' | 'tag'`, `contentType?: string`
- **Features**: List of `TagBadge` with truncation to N visible and a "+N" counter for the rest

#### TagFilter

- **File**: `ui/TagFilter.vue`
- **Props**: `tags: Tag[]`, `currentTags: string[]`, `basePath: string`
- **Features**: Tag filter bar with multiple selection. "All" button to clear. Navigates with Inertia preserving state and scroll. Dynamic styles by tag color with selection ring

#### NotificationToast

- **File**: `ui/NotificationToast.vue`
- **Props**: -- (reads global state from `useNotifications`)
- **Features**: Toast notification system positioned in upper-right corner. Types: success, error, warning, info. Enter/exit transitions. Close button. Type-based icon (Lucide). `Teleport` to body

#### EmptyState

- **File**: `ui/EmptyState.vue`
- **Props**: `icon: 'book' | 'calendar' | 'document' | 'photo' | 'trophy'`, `title: string`, `description?: string`
- **Slots**: `default` (actions)
- **Features**: Empty state with icon (Lucide), title, optional description and slot for action buttons

#### ConfirmDialog

- **File**: `ui/ConfirmDialog.vue`
- **Props**: `modelValue: boolean`, `title: string`, `message: string`, `confirmLabel?: string`, `cancelLabel?: string`, `confirmVariant?: 'primary' | 'danger'`
- **Emits**: `update:modelValue`, `confirm`, `cancel`
- **Features**: Confirmation modal dialog with native `<dialog>`

#### ImagePlaceholder

- **File**: `ui/ImagePlaceholder.vue`
- **Props**: `variant: 'event' | 'article' | 'gallery'`, `height?: string`, `iconSize?: string`
- **Features**: Image placeholder with gradient and an SVG icon based on the content type

#### SocialLinks

- **File**: `ui/SocialLinks.vue`
- **Props**: `links: SocialMediaLinks`, `size?: 'sm' | 'md'`, `variant?: 'light' | 'dark'`
- **Features**: Social media icons (Facebook, Instagram, Twitter/X, Discord, TikTok, Bluesky, Telegram) with inline SVGs. "Dark" variant for dark backgrounds (footer), "light" for light backgrounds

### Events

#### EventCard

- **File**: `events/EventCard.vue`
- **Props**: `event: Event`, `variant?: 'default' | 'compact'`
- **Features**: Event card with image (or placeholder), category badge over image, "upcoming" badge in default variant, visual date badge (day/month) in compact variant. Title, additional tags, date range, location, description excerpt. Full clickable link with hover scale effect

#### EventList

- **File**: `events/EventList.vue`
- **Props**: `events: Event[]`, `columns?: GridColumns` (default: 3)
- **Features**: Responsive grid of `EventCard` with empty state (`EmptyState`). Uses `useGridLayout` for grid classes

#### EventsSection

- **File**: `events/EventsSection.vue`
- **Props**: `events: Event[]`, `maxEvents?: number` (default: 2)
- **Features**: Home page section. 2-column grid with `EventCard` (compact variant) + `CalendarWidget` in the third column. "View all" link

### Articles

#### ArticleCard

- **File**: `articles/ArticleCard.vue`
- **Props**: `article: Article`
- **Features**: Article card with image (or placeholder), category badge over image, title, additional tags, author with publication date, excerpt. Full clickable link

#### ArticleList

- **File**: `articles/ArticleList.vue`
- **Props**: `articles: Article[]`, `columns?: GridColumns` (default: 3)
- **Features**: Responsive grid of `ArticleCard` with empty state. Uses `useGridLayout`

### Gallery

#### GalleryCard

- **File**: `gallery/GalleryCard.vue`
- **Props**: `gallery: Gallery`
- **Features**: Gallery card with cover image (or placeholder), category badge, photo count in bottom badge, title, additional tags, description excerpt

#### GalleryGrid

- **File**: `gallery/GalleryGrid.vue`
- **Props**: `galleries: Gallery[]`, `columns?: GridColumns` (default: 3)
- **Features**: Responsive grid of `GalleryCard` with empty state

#### PhotoLightbox

- **File**: `gallery/PhotoLightbox.vue`
- **Props**: `photos: Photo[]`, `currentIndex: number`, `isOpen: boolean`
- **Emits**: `close`, `next`, `prev`
- **Features**: Full-screen photo viewer. Navigation arrows, close with button or Escape, keyboard arrow navigation, focus trap for accessibility, photo counter, caption, `Teleport` to body. Images loaded with Cloudinary transformations (1920px)

### Calendar

#### CalendarWidget

- **File**: `calendar/CalendarWidget.vue`
- **Props**: --
- **Features**: Compact calendar widget for the home page. Wraps `EventCalendar` in compact mode with tooltips. Click on any event or date navigates to `/calendario`

#### EventCalendar

- **File**: `calendar/EventCalendar.vue`
- **Props**: `compact?: boolean`, `showTooltips?: boolean`, `navigateOnClick?: boolean`
- **Emits**: `eventSelect`, `calendarClick`, `eventsLoaded`
- **Features**: FullCalendar calendar with `dayGrid` and `interaction` plugins. Loads events via API (`/eventos/calendario`). Event cache for quick access. Dynamic locale support (`useCalendarLocale`). Tooltips on hover (`EventTooltip`). Loading indicator. Configurable toolbar (compact vs full)

#### EventDetailPanel

- **File**: `calendar/EventDetailPanel.vue`
- **Props**: `event: CalendarEvent | null`
- **Features**: Side panel for event detail. Shows image, title, tags, date, location, truncated description, prices. "View event" button. Empty state when no event is selected

#### EventTooltip

- **File**: `calendar/EventTooltip.vue`
- **Props**: `event: CalendarEvent | null`, `x: number`, `y: number`, `visible: boolean`
- **Features**: Floating tooltip that follows the cursor. Shows event title, date and location. `Teleport` to body with opacity transition

### Hero

#### HeroSlider

- **File**: `hero/HeroSlider.vue`
- **Props**: `slides: HeroSlide[]`, `autoplayInterval?: number` (default: 5000), `showArrows?: boolean`
- **Features**: Full-screen hero carousel (80vh). Autoplay with pause on hover. Navigation arrows, slide indicators. Each slide with background image (Cloudinary full screen), title, subtitle, button with link. Gradient fallback if no slides. Uses `useHeroSlider`

### Profile

#### ProfileHeader

- **File**: `profile/ProfileHeader.vue`
- **Props**: `user: User`
- **Features**: Profile header with avatar (editable by upload), username, display name, registration date. Built-in avatar change form

#### ProfileSidebar

- **File**: `profile/ProfileSidebar.vue`
- **Props**: `tabs: ProfileTab[]`, `activeTabId: string`
- **Emits**: `select-tab`
- **Features**: Side navigation sidebar (desktop) with icons, labels, optional badges. Support for tab hierarchy (child items with indentation)

#### ProfileTabBar

- **File**: `profile/ProfileTabBar.vue`
- **Props**: `tabs: ProfileTab[]`, `activeTabId: string`
- **Emits**: `select-tab`
- **Features**: Horizontal tab bar (mobile) with horizontal scroll, icons and badges

#### ProfileTabIcon

- **File**: `profile/ProfileTabIcon.vue`
- **Props**: `icon: ProfileTabIcon`
- **Features**: Renders the corresponding Lucide icon for the profile tab type

#### ProfileAccountTab

- **File**: `profile/ProfileAccountTab.vue`
- **Props**: `user: User`
- **Features**: Account tab with two forms: profile information (name, display name, email) and password change. Uses `BaseCard` for visual grouping. Success/error notifications via `useNotifications`

### Forms

#### FormToggle

- **File**: `form/FormToggle.vue`
- **Props**: `modelValue: boolean`, `label?: string`, `disabled?: boolean`, `id?: string`, `name?: string`
- **Emits**: `update:modelValue`
- **Features**: Toggle switch with animation, v-model support, associated label, disabled state

#### FormCheckbox

- **File**: `form/FormCheckbox.vue`
- **Props**: `modelValue: boolean`, `label?: string`, `disabled?: boolean`, `id?: string`, `name?: string`
- **Emits**: `update:modelValue`
- **Features**: Styled checkbox with v-model support, associated label

#### FormCheckboxGroup

- **File**: `form/FormCheckboxGroup.vue`
- **Props**: `modelValue: string[]`, `groups: CheckboxGroup[]`, `collapsible?: boolean`, `defaultOpen?: boolean`, `error?: string`
- **Emits**: `update:modelValue`
- **Features**: Checkbox group organized by collapsible categories (HeadlessUI `Disclosure`). Each group can have severity (mild/moderate/severe). Each option with name and description

#### FormCheckboxWithTooltip

- **File**: `form/FormCheckboxWithTooltip.vue`
- **Props**: `modelValue: string[]`, `value: string`, `label: string`, `description?: string`, `id?: string`
- **Emits**: `update:modelValue`
- **Features**: Checkbox with information tooltip (`FormTooltip`) for extended description

#### FormSelect

- **File**: `form/FormSelect.vue`
- **Props**: `modelValue: string | number | null`, `options: Option[]`, `placeholder?: string`, `disabled?: boolean`, `error?: string`, `id?: string`, `name?: string`
- **Emits**: `update:modelValue`
- **Features**: Styled select dropdown with HeadlessUI `Listbox`

#### FormCombobox

- **File**: `form/FormCombobox.vue`
- **Props**: `modelValue: string | number | null`, `options: Option[]`, `optionLabel: string`, `optionValue: string`, `placeholder?: string`, `searchable?: boolean`, `disabled?: boolean`, `error?: string`, `id?: string`
- **Emits**: `update:modelValue`
- **Features**: Combobox with built-in search. HeadlessUI `Combobox` with text-based option filtering

#### FormRadioGroup

- **File**: `form/FormRadioGroup.vue`
- **Props**: `modelValue: string`, `options: RadioOption[]`, `name: string`, `label?: string`, `error?: string`, `disabled?: boolean`
- **Emits**: `update:modelValue`
- **Features**: Radio button group with HeadlessUI `RadioGroup`. Each option with label and optional description

#### FormNumberInput

- **File**: `form/FormNumberInput.vue`
- **Props**: `modelValue: number | null`, `min?: number`, `max?: number`, `step?: number`, `disabled?: boolean`, `error?: string`, `id?: string`, `name?: string`, `required?: boolean`, `placeholder?: string`
- **Emits**: `update:modelValue`
- **Features**: Numeric input with range validation (min/max), configurable step, error state

#### FormTagsInput

- **File**: `form/FormTagsInput.vue`
- **Props**: `modelValue: string[]`, `placeholder?: string`, `maxTags?: number` (default: 10), `maxLength?: number` (default: 200), `disabled?: boolean`, `error?: string`, `id?: string`, `name?: string`
- **Emits**: `update:modelValue`
- **Features**: Tag input with removable chips. Add with Enter, remove with Backspace or button. Tag and length limit

#### FormTooltip

- **File**: `form/FormTooltip.vue`
- **Props**: `content: string`, `position?: 'top' | 'bottom' | 'left' | 'right'`, `maxWidth?: 'xs' | 'sm' | 'md' | 'lg'`
- **Features**: Positionable informational tooltip with text content. Hover activation

### Contact

#### ContactForm

- **File**: `contact/ContactForm.vue`
- **Props**: --
- **Features**: Contact form with name, email, message fields and hidden honeypot field (anti-spam). Sends via Inertia POST to `/contacto`. Displays success/error flash messages. Uses `useNotifications` and `useFlashMessages`

### Map

#### LocationMap

- **File**: `map/LocationMap.vue`
- **Props**: `location: { name: string, address: string, lat: number, lng: number, zoom: number } | null`
- **Features**: Interactive Leaflet map with OpenStreetMap. Marker with popup showing name and address. Async component (`defineAsyncComponent`) for lazy loading

### Search

#### SearchInput

- **File**: `search/SearchInput.vue`
- **Props**: `initialQuery?: string`, `autoFocus?: boolean`
- **Features**: Search input with icon, 300ms debounce, automatic search on typing (min. 2 characters), submit with Enter. Navigates with Inertia preserving state

---

## 4. Composables

Located in `src/resources/js/composables/`.

### useEvents

- **Parameters**: --
- **Returns**: `{ formatEventDate, formatDateRange, formatPrice, isUpcoming, getExcerpt, upcomingEvents, pastEvents }`
- **Dependencies**: `vue-i18n`, `stripHtml`
- **Purpose**: Event date formatting (smart range: same day, same month, different month), price formatting in euros, future/past status check, clean text extraction for excerpts, event filtering and sorting

### useArticles

- **Parameters**: --
- **Returns**: `{ formatPublishedDate, getExcerpt, getAuthorDisplayName }`
- **Dependencies**: `vue-i18n`, `stripHtml`
- **Purpose**: Localized publication date formatting, excerpt generation (prioritizes article `excerpt`, fallback to clean content), author name resolution (prioritizes `displayName`)

### useGallery

- **Parameters**: --
- **Returns**: `{ formatGalleryDate, getPhotoCount, getCoverPhoto, getGalleryExcerpt }`
- **Dependencies**: `vue-i18n`
- **Purpose**: Gallery date formatting, photo count text, cover photo retrieval, description truncation

### useLightbox

- **Parameters**: `photos: Ref<Photo[]>`
- **Returns**: `{ isOpen, currentIndex, currentPhoto, open, close, next, prev, goTo }`
- **Dependencies**: Vue reactivity
- **Purpose**: Lightbox state management. Circular navigation between photos, open/close control, body scroll locking on open. Automatic cleanup on `onUnmounted`

### useSeo

- **Parameters**: `options: SeoOptions` (`title?`, `description?`, `image?`, `url?`, `canonical?`, `type?`)
- **Returns**: `void`
- **Dependencies**: `@unhead/vue`, Inertia `usePage`, `vue-i18n`
- **Purpose**: SEO meta tag management. Generates full title (`title - siteName`), description truncated to 160 chars, canonical URL, Open Graph (title, description, type, url, site_name, image), Twitter Card

### usePagination

- **Parameters**: `paginated: MaybeRefOrGetter<PaginatedResponse<T>>`
- **Returns**: `{ firstItemNumber, lastItemNumber, hasPagination, goToPage, goToPrev, goToNext, canGoPrev, canGoNext, isNavigating, handlePrev, handleNext }`
- **Dependencies**: Inertia `router`
- **Purpose**: Reusable pagination logic. Calculates displayed item numbers, checks for pagination, manages page navigation with loading state

### useAuth

- **Parameters**: --
- **Returns**: `{ user, isAuthenticated, isEmailVerified, isAdmin, isEditor, canManageContent, authSettings, can, canAny, canAll, hasRole, hasAnyRole, logout }`
- **Dependencies**: Inertia `usePage`, `router`
- **Purpose**: Access to authenticated user from Inertia props. Role checks (admin, editor) with support for `roles` array and legacy `role` field. Granular permission system (`can`, `canAny`, `canAll`). Auth configuration (registration/login enabled, email verification). Logout via POST

### useNotifications

- **Parameters**: --
- **Returns**: `{ notifications, addNotification, removeNotification, clearAll, success, error, info, warning }`
- **Dependencies**: Vue reactivity
- **Purpose**: Global toast notification system. Singleton state (module-level `ref`). Auto-removal with configurable timeout (5s default). Type shortcuts (`success()`, `error()`, etc.)

### useTags

- **Parameters**: `tags: MaybeRef<Tag[] | undefined>`
- **Returns**: `{ categoryTag, additionalTags, hasTags }`
- **Dependencies**: --
- **Purpose**: Separation of tags into primary category (first tag without `parentId`) and additional tags (tags with `parentId`). Boolean indicator for whether tags exist

### useRoutes

- **Parameters**: --
- **Returns**: `RouteDefinitions` (object with all application routes)
- **Dependencies**: --
- **Purpose**: Centralized route definitions. Eliminates hardcoded URLs. Public routes, content routes (with `show(slug)` functions), authentication, profile, admin and contact routes. All URLs in Spanish

### useHeroSlider

- **Parameters**: `slides: Ref<HeroSlide[]>`, `autoplayInterval?: number` (default: 5000)
- **Returns**: `{ currentIndex, currentSlide, isPlaying, hasMultipleSlides, next, prev, goTo, pause, resume }`
- **Dependencies**: Vue lifecycle hooks
- **Purpose**: Hero carousel logic. Autoplay with configurable interval, pause/resume, circular navigation. Automatic interval cleanup on `onUnmounted`

### useFavicons

- **Parameters**: --
- **Returns**: `void`
- **Dependencies**: `useAppStore`, Inertia `usePage`
- **Purpose**: Dynamic favicon switching based on active theme (light/dark). Supports custom favicons from settings or fallback to static files in `/favicons/{theme}/`

### useFlashMessages

- **Parameters**: --
- **Returns**: `{ success, error, hasMessages }`
- **Dependencies**: Inertia `usePage`
- **Purpose**: Reactive access to server flash messages (`flash.success`, `flash.error`) shared by Inertia

### useProfileTabs

- **Parameters**: --
- **Returns**: `{ tabs, activeTabId, activeTab, setActiveTab, isActiveTab }`
- **Dependencies**: `vue-i18n`, `useModuleSlots`
- **Purpose**: Profile tab management. Base "account" tab + dynamic module tabs from the `profile-sections` slot. Each module can register tabs with icon, label, badge and hierarchy

### useModuleSlots

- **Parameters**: --
- **Returns**: `{ moduleSlots, getSlotComponents, hasSlotComponents }`
- **Dependencies**: Inertia `usePage`, Vue `defineAsyncComponent`
- **Purpose**: Module slot system. Reads module registrations from Inertia props (`moduleSlots`). Resolves module components as async components: in development uses build-time glob with HMR, in production uses dynamic loading via module Vite manifest. Injects module CSS. Merges static props with Inertia data

### useCalendarLocale

- **Parameters**: --
- **Returns**: `ComputedRef<LocaleInput | string>`
- **Dependencies**: `vue-i18n`, `@fullcalendar/core/locales/es`
- **Purpose**: Dynamic locale resolution for FullCalendar based on the active i18n language

### useGridLayout

- **Parameters**: `columns: MaybeRefOrGetter<GridColumns>` (1, 2, 3 or 4)
- **Returns**: `{ gridClasses }`
- **Dependencies**: --
- **Purpose**: Responsive CSS grid class generation based on desired column count

### useMediaQuery

- **Parameters**: `query: string` (CSS media query)
- **Returns**: `Ref<boolean>`
- **Dependencies**: `MediaQueryList` API
- **Purpose**: Reactive media query detection. SSR-compatible (returns `false`). Listener cleanup on `onUnmounted`

### useEventDateBadge

- **Parameters**: `localeParam?: MaybeRef<string>` (optional, fallback to i18n locale)
- **Returns**: `{ getDateBadge: (dateString) => { day, month } }`
- **Dependencies**: `vue-i18n` (optional)
- **Purpose**: Generates visual date badge with numeric day and abbreviated month in the active language (ES: ENE, FEB... / EN: JAN, FEB...)

---

## 5. Stores (Pinia)

Located in `src/resources/js/stores/`.

### useAppStore

- **File**: `useAppStore.ts`
- **ID**: `'app'`

| Type | Name | Description |
|------|------|-------------|
| State | `locale` | Active language (`'es' \| 'en'`) |
| State | `isSidebarOpen` | Mobile sidebar state |
| State | `isLoading` | Global loading indicator |
| State | `themeMode` | Theme mode (`'light' \| 'dark' \| 'system'`) |
| State | `systemPrefersDark` | Operating system preference |
| State | `themeSettings` | Server theme configuration (`ThemeSettings \| null`) |
| Getter | `currentLocale` | Current locale |
| Getter | `isDarkMode` | Whether dark mode is active (resolves `system` based on `systemPrefersDark`) |
| Getter | `isThemeToggleVisible` | Whether the theme toggle button should be shown |
| Action | `setLocale(newLocale)` | Changes the language |
| Action | `toggleSidebar()` | Toggles sidebar |
| Action | `setLoading(loading)` | Sets loading state |
| Action | `setThemeSettings(settings)` | Stores server theme configuration |
| Action | `initTheme(serverTheme?)` | Initializes theme: reads localStorage, listens for `prefers-color-scheme`, applies `dark` class |
| Action | `setThemeMode(mode)` | Changes theme mode and persists to localStorage |
| Action | `cycleThemeMode()` | Cycles between system -> light -> dark |

- **Persistence**: `themeMode` is persisted in `localStorage` under the key `'themeMode'`

---

## 6. TypeScript types

Located in `src/resources/js/types/`.

### index.ts

Re-exports `models.ts` and `inertia.d.ts`. Also defines:

| Type | Properties |
|------|------------|
| `PaginatedResponse<T>` | `data: T[]`, `meta: { currentPage, lastPage, perPage, total }`, `links: { first, last, prev, next }` |
| `ApiResponse<T>` | `data: T`, `message?: string` |
| `FlashMessages` | `success?: string`, `error?: string` |

### models.ts

| Type | Main properties |
|------|-----------------|
| `User` | `id`, `name`, `displayName`, `email`, `pendingEmail`, `avatarPublicId`, `role` (deprecated), `emailVerified`, `createdAt`, `roles?: string[]`, `permissions?: string[]` |
| `UserRole` | `'admin' \| 'editor' \| 'member'` (deprecated) |
| `AuthSettings` | `registrationEnabled`, `loginEnabled`, `emailVerificationRequired` |
| `RegisterFormData` | `name`, `email`, `password`, `password_confirmation` |
| `LoginFormData` | `email`, `password`, `remember` |
| `ForgotPasswordFormData` | `email` |
| `ResetPasswordFormData` | `token`, `email`, `password`, `password_confirmation` |
| `UpdateProfileFormData` | `name`, `display_name`, `email`, `avatar: File \| null` |
| `ChangePasswordFormData` | `current_password`, `password`, `password_confirmation` |
| `Tag` | `id`, `name`, `slug`, `color`, `parentId` |
| `DownloadLink` | `label`, `url`, `description` |
| `Event` | `id`, `title`, `slug`, `description`, `startDate`, `endDate`, `location`, `imagePublicId`, `memberPrice`, `nonMemberPrice`, `isPublished`, `createdAt`, `updatedAt`, `downloadLinks: DownloadLink[]`, `tags: Tag[]` |
| `Article` | `id`, `title`, `slug`, `content`, `excerpt`, `featuredImagePublicId`, `isPublished`, `publishedAt`, `author: User`, `createdAt`, `updatedAt`, `tags: Tag[]` |
| `Gallery` | `id`, `title`, `slug`, `description`, `coverImagePublicId`, `isPublished`, `photos?: Photo[]`, `photoCount?: number`, `createdAt`, `updatedAt`, `tags: Tag[]` |
| `Photo` | `id`, `imagePublicId`, `caption`, `sortOrder` |
| `HeroSlide` | `id`, `title`, `subtitle`, `buttonText`, `buttonUrl`, `imagePublicId` |
| `EventFilters` | `search?`, `upcoming?`, `tags?`, `page?` |
| `ArticleFilters` | `search?`, `authorId?`, `tags?`, `page?` |
| `GalleryFilters` | `search?`, `tags?`, `page?` |
| `CalendarEvent` | `id`, `title`, `slug`, `description`, `start`, `end`, `location`, `imagePublicId`, `memberPrice`, `nonMemberPrice`, `url`, `tags: Tag[]` |
| `ActivityIcon` | `'dice' \| 'sword' \| 'book' \| 'users' \| 'calendar' \| 'map' \| 'trophy' \| 'puzzle' \| 'sparkles' \| 'heart'` |
| `Activity` | `icon: ActivityIcon`, `title`, `description` |
| `JoinStep` | `title`, `description` |
| `ContactFormData` | `name`, `email`, `message`, `website` (honeypot) |
| `SocialMediaLinks` | `facebook?`, `instagram?`, `twitter?`, `discord?`, `tiktok?`, `bluesky?`, `telegram?` |

### navigation.ts

| Type | Properties |
|------|------------|
| `MenuItem` | `id`, `label`, `href`, `target: '_self' \| '_blank'`, `icon`, `children: MenuItem[]`, `isActive` |
| `Navigation` | `header: MenuItem[]`, `footer: MenuItem[]` |

### legal.ts

| Type | Properties |
|------|------------|
| `LegalPageProps` | `title`, `content`, `lastUpdated` |

### slots.ts

| Type | Properties |
|------|------------|
| `SlotPosition` | `'before-header' \| 'after-header' \| 'before-content' \| 'after-content' \| 'before-footer' \| 'after-footer' \| 'event-detail-actions' \| 'game-table-registration' \| 'campaign-detail-actions' \| 'profile-sections'` |
| `ProfileTabMeta` | `tabId?`, `icon: ProfileTabIcon`, `labelKey`, `badgeKey?`, `parentId?` |
| `SlotRegistration` | `slot`, `component`, `module`, `order`, `props`, `dataKeys`, `profileTab?` |
| `ModuleSlots` | `Record<string, SlotRegistration[]>` |

### profile.ts

| Type | Properties |
|------|------------|
| `ProfileTab` | `id`, `label`, `icon: ProfileTabIcon`, `badge?`, `isModuleTab?`, `parentId?` |
| `ProfileTabIcon` | `'user' \| 'lock' \| 'dice' \| 'trophy' \| 'calendar' \| 'cog' \| 'pencil-square'` |
| `ProfileTabMetadata` | `icon`, `label`, `order` |

### inertia.d.ts

Extends Inertia `PageProps` with:

| Property | Type |
|----------|------|
| `appName` | `string` |
| `appDescription` | `string` |
| `siteLogoLight` | `string \| null` |
| `siteLogoDark` | `string \| null` |
| `favicons` | `FaviconSettings` |
| `theme` | `ThemeSettings` |
| `auth` | `{ user: User \| null }` |
| `authSettings` | `AuthSettings` |
| `flash` | `{ success?, error?, warning?, info? }` |
| `errors` | `Record<string, string>` |
| `moduleSlots` | `ModuleSlots` |

Exported types:

| Type | Properties |
|------|------------|
| `ThemeSettings` | `cssVariables`, `darkModeDefault`, `darkModeToggleVisible`, `fontHeading`, `fontBody` |
| `FaviconSettings` | `light: string \| null`, `dark: string \| null` |

### env.d.ts

| Type | Properties |
|------|------------|
| `ImportMetaEnv` | `VITE_CLOUDINARY_CLOUD_NAME`, `VITE_CLOUDINARY_PREFIX` |

---

## 7. Utilities

Located in `src/resources/js/utils/`.

### cloudinary.ts

| Function | Purpose |
|----------|---------|
| `buildImageUrl(publicId, transformations)` | Generates Cloudinary URL with transformations (width, height, crop, quality, format, gravity). Auto-prefixes `VITE_CLOUDINARY_PREFIX`, removes extensions |
| `buildCardImageUrl(publicId)` | Preset: 600x400, fill |
| `buildHeroImageUrl(publicId)` | Preset: 1200x600, fill |
| `buildAvatarUrl(publicId, size)` | Preset: size x size, fill, gravity face |
| `buildGalleryImageUrl(publicId)` | Preset: 800x600, fit |
| `buildMosaicLargeUrl(publicId)` | Preset: 800x600, fill |
| `buildMosaicSmallUrl(publicId)` | Preset: 400x300, fill |
| `buildLightboxImageUrl(publicId)` | Preset: 1920, fit |
| `buildFullScreenHeroImageUrl(publicId)` | Preset: 1920x1080, fill, gravity auto |

### color.ts

| Function | Purpose |
|----------|---------|
| `getLuminance(hex)` | Calculates relative luminance per WCAG 2.0 |
| `getContrastTextColor(hex)` | Determines whether text should be dark or light based on background |
| `hexToRgb(hex)` | Converts hex to `{ r, g, b }` object |
| `adjustColorBrightness(hex, percent)` | Lightens (positive) or darkens (negative) a hex color |

### html.ts

| Function | Purpose |
|----------|---------|
| `stripHtml(html)` | Removes all HTML tags from a string with regex |

### icons.ts

| Export | Purpose |
|--------|---------|
| `activityIconMap` | Mapping of `ActivityIcon` to Lucide components (Dices, Swords, BookOpen, etc.) |
| `emptyStateIconMap` | Mapping of empty state icons to Lucide components |
| `profileTabIconMap` | Mapping of `ProfileTabIcon` to Lucide components |
| `notificationIconMap` | Mapping of `NotificationType` to Lucide components (CircleCheck, CircleAlert, etc.) |

### resolveModulePage.ts

| Function | Purpose |
|----------|---------|
| `setModulePageMapping(mapping)` | Sets the page prefix to module mapping |
| `getModulePageMapping()` | Gets the current mapping |
| `getModuleForPage(pageName)` | Returns the module name for a page name |
| `isModulePage(pageName)` | Checks if a page belongs to a module |
| `createPageResolver(options)` | Creates the page resolver for Inertia. Tries: 1) build-time glob, 2) dynamic loading via Vite manifest. Supports modules installed post-build |

### moduleTranslations.ts

| Function | Purpose |
|----------|---------|
| `loadAllModuleTranslations(i18n)` | Loads module translations available at build-time (via glob) and merges them into i18n messages |
| `loadModuleTranslationsFromProps(i18n, moduleTranslations)` | Loads module translations from Inertia props (runtime fallback for post-build modules) |
| `isModuleTranslationsLoaded(moduleName, locale)` | Checks if translations for a module/locale have already been loaded |

---

## 8. Module frontend system

Modules extend the GuildForge frontend through several mechanisms:

### ModuleSlot component

The `ModuleSlot` component (`components/layout/ModuleSlot.vue`) acts as an insertion point. It accepts a `name` prop of type `SlotPosition` and renders all module components registered for that position.

**Available positions**:
- **Layout**: `before-header`, `after-header`, `before-content`, `after-content`, `before-footer`, `after-footer`
- **Page-specific**: `event-detail-actions`, `game-table-registration`, `campaign-detail-actions`, `profile-sections`

### useModuleSlots composable

Reads slot registrations from Inertia shared props (`moduleSlots`). For each registration, resolves the module Vue component as an async component:

1. **Production**: always uses dynamic loading via module Vite manifest (`/build/modules/{module}/manifest.json`). Injects module CSS
2. **Development**: tries build-time glob (HMR), fallback to dynamic loading

### Module page resolution

`resolveModulePage.ts` allows modules to register their own Inertia pages. The backend sends a prefix-to-module mapping, and the resolver tries to load the page:

1. Build-time glob (modules present at compile time)
2. Dynamic loading via Vite manifest (modules installed afterwards)

### Module translation loading

`moduleTranslations.ts` merges module translations into the i18n instance:

1. **Build-time**: via `import.meta.glob` of `modules/*/resources/js/locales/{es,en}.ts` files
2. **Runtime**: from Inertia shared props (`moduleTranslations`) for post-build modules

### Module profile tabs

Modules can register tabs on the profile page through the `profile-sections` slot. Each registration includes tab metadata (`ProfileTabMeta`): icon, translation key, badge and hierarchy. The `useProfileTabs` composable combines the base "account" tab with module tabs.

### Existing modules with frontend

| Module | Components | Pages | Types |
|--------|------------|-------|-------|
| `game-tables` | `StatusBadge`, `FormatBadge`, `RegistrationStatus`, `ContentWarningBadge`, `SafetyToolBadge`, `GameTableCard`, `GameTableCardSkeleton`, `GameTableFilters`, `FormTabs`, `GuestRegistrationModal`, `RegistrationButton`, `EventTablesLink`, `EventCreateTableButton`, `GameMasterSection`, `ProfileGameTablesSection`, `ProfileGameTablesFilters`, `ProfileParticipationCard`, `ProfileCreatedTableCard`, `ProfileCreatedTablesSection`, `ProfileCreatedCampaignCard`, `ProfileCreatedCampaignsSection` | `GameTables/Index`, `GameTables/Show`, `GameTables/Calendar`, `GameTables/Create`, `GameTables/Edit`, `GameTables/CreateNotEligible`, `GameTables/CancelRegistration`, `GameTables/MyTables`, `Campaigns/Index`, `Campaigns/Show`, `Campaigns/Create`, `Campaigns/Edit`, `Campaigns/CreateNotEligible`, `Campaigns/MyCampaigns` | `gametables.ts`, `registration.ts` |
| `tournaments` | `TournamentCard`, `TournamentList`, `TournamentStatusFilter`, `MyTournamentCard`, `EventTournamentSection`, `ProfileTournamentCard`, `ProfileTournamentsSection` | `Index`, `Tournaments/Show`, `Tournaments/Standings`, `Tournaments/Rounds`, `Tournaments/CheckIn`, `Tournaments/CancelRegistration`, `Tournaments/MyTournaments` | `tournaments.ts` |
| `event-registrations` | `RegistrationStatus`, `RegistrationForm`, `RegistrationButton`, `UserRegistrations` | -- | `registration.ts` |
| `cookie-consent` | `CookieBanner`, `CookieSettingsModal` | -- | `cookie-consent.ts` |
| `announcements` | `AnnouncementBanner`, `AnnouncementCard` | -- | `announcement.ts` |
| `venue-bookings` | `BookingStatusBadge`, `ResourceSelector`, `BookingForm`, `BookingCard`, `BookingDetailModal`, `BookingTooltip`, `BookingSlotPicker`, `BookingCalendar`, `ProfileBookingsSection` | `VenueBookings/Index`, `VenueBookings/MyBookings` | `bookings.ts`, `profile.ts` |

---

## 9. Internationalization

### Supported languages

| Language | Code | File |
|----------|------|------|
| Spanish | `es` | `src/resources/js/locales/es.ts` |
| English | `en` | `src/resources/js/locales/en.ts` |

### Translation file structure

The files export a `default` object with keys grouped by section:

```
common.*        -- Global text (home, events, calendar, articles, gallery, about us, common actions)
events.*        -- Events section (title, upcoming, past, date, location, prices)
articles.*      -- Articles section (title, subtitle, published on, by)
gallery.*       -- Gallery section (title, subtitle, photos, close, next, previous)
calendar.*      -- Calendar (title, today, view full, select event, loading, error)
home.*          -- Home page (subtitle, upcoming events, latest articles, featured gallery)
about.*         -- About us page (title, history, contact, location, activities, join)
auth.*          -- Authentication (login, registration, profile, verification, password recovery)
layout.*        -- Layout (branding, copyright, theme selector, menu)
search.*        -- Search (title, placeholder, results, no results, min. characters)
tags.*          -- Tags (filter, all)
legal.*         -- Legal pages (last updated)
a11y.*          -- Accessibility (skip to content, view event, view article, photo counter)
buttons.*       -- Buttons (close)
```

### Module translations

Each module can include its own translation files in `modules/{module}/resources/js/locales/{es,en}.ts`. They are merged into the global i18n instance both at build-time (glob) and at runtime (Inertia props). Modules with translations: `game-tables`, `tournaments`, `venue-bookings`.
