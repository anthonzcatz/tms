# MAUI Admin Dashboard Design Prompt

## Overview
Create a professional, clean, and responsive admin dashboard UI using .NET MAUI that matches the design quality of the Falcon Admin Dashboard template. The design should be modern, professional, with excellent color combinations and a polished user experience.

## Color Palette

### Primary Colors
- **Primary Blue**: `#2C7BE5` (Bootstrap primary equivalent)
- **Primary Dark**: `#1A5CBE` (Hover state)
- **Primary Light**: `#4A90E2` (Light accent)

### Semantic Colors
- **Success Green**: `#00D97E` (For positive indicators, growth)
- **Warning Yellow**: `#F6C343` (For warnings, attention)
- **Danger Red**: `#E63757` (For errors, deletions)
- **Info Light Blue**: `#39ADF0` (For informational content)

### Neutral Colors
- **Background Primary**: `#FFFFFF` (White)
- **Background Secondary**: `#F9FAFD` (Light gray for cards)
- **Background Tertiary**: `#E9ECEF` (Darker gray for headers/footers)
- **Text Primary**: `#344050` (Dark gray for headings)
- **Text Secondary**: `#6C757D` (Medium gray for body text)
- **Text Muted**: `#95A5A6` (Light gray for subtle text)
- **Border Color**: `#E9ECEF` (Subtle borders)

### Gradient Backgrounds
- **Chart Gradient**: Linear gradient from `#2C7BE5` to `#00D97E` (for charts/cards)
- **Card Gradient**: Subtle gradient overlays for depth

## Typography

### Font Family
- **Primary**: System default sans-serif (San Francisco on iOS, Segoe UI on Windows, Roboto on Android)
- **Monospace**: For code, numbers, data (Consolas, Monaco, monospace)

### Font Sizes
- **H1**: 32px (Page titles)
- **H2**: 24px (Section headers)
- **H3**: 20px (Card titles)
- **H4**: 16px (Subheaders)
- **H5**: 14px (Body text)
- **H6**: 12px (Small text, labels)
- **FS-9**: 11px (Tiny text, metadata)
- **FS-10**: 12px (Small text)
- **FS-11**: 13px (Regular small text)

### Font Weights
- **Light**: 300
- **Normal**: 400
- **Medium**: 500
- **Semi-bold**: 600
- **Bold**: 700

## Component Design

### 1. Cards
- **Background**: White with subtle shadow
- **Border Radius**: 12px
- **Shadow**: `0 2px 6px rgba(0,0,0,0.08)`
- **Padding**: 20px
- **Header**: Light gray background (`#F9FAFD`) with 12px padding
- **Footer**: Light gray background with 8px padding
- **Hover Effect**: Slight elevation on hover (shadow increases)

### 2. Buttons
- **Primary Button**:
  - Background: Primary Blue (`#2C7BE5`)
  - Text: White
  - Border Radius: 6px
  - Padding: 10px 20px
  - Hover: Darker blue (`#1A5CBE`)
  - Active: Slightly darker with scale effect

- **Outline Button**:
  - Background: Transparent
  - Border: 1px solid Primary Blue
  - Text: Primary Blue
  - Hover: Light blue background

- **Link Button**:
  - Background: Transparent
  - Text: Primary Blue
  - Hover: Underline

- **Icon Button**:
  - Background: Transparent
  - Icon only
  - Hover: Light gray background

### 3. Navigation

#### Sidebar (Vertical)
- **Width**: 250px (collapsed: 70px)
- **Background**: Dark gradient (`#1A1D2E` to `#252A40`)
- **Text**: White
- **Active Item**: Primary Blue background with white text
- **Hover Item**: Lighter background (`#2E3550`)
- **Icon Size**: 20px
- **Spacing**: 12px between items

#### Top Navigation
- **Height**: 60px
- **Background**: White with bottom border
- **Logo**: Left aligned
- **Menu Items**: Center aligned
- **User Profile**: Right aligned
- **Shadow**: Subtle bottom shadow

### 4. Tables
- **Header Background**: Light gray (`#F9FAFD`)
- **Header Text**: Dark gray, medium weight
- **Row Background**: White
- **Row Hover**: Light blue tint (`#F0F4FF`)
- **Border**: 1px solid light gray
- **Padding**: 12px
- **Font Size**: 14px

### 5. Forms
- **Input Fields**:
  - Border: 1px solid light gray
  - Border Radius: 6px
  - Padding: 10px 12px
  - Focus: Primary Blue border
  - Background: White

- **Labels**: Dark gray, medium weight, 12px

- **Select Dropdown**: Same as input fields with chevron icon

### 6. Charts
- **Chart Background**: White or gradient
- **Grid Lines**: Light gray, dashed
- **Axis Labels**: Medium gray, 12px
- **Legend**: Bottom aligned, colored dots
- **Tooltip**: Dark background with white text

### 7. Badges
- **Success**: Green background, white text
- **Warning**: Yellow background, dark text
- **Danger**: Red background, white text
- **Info**: Blue background, white text
- **Border Radius**: 12px (pill shape)
- **Padding**: 4px 12px
- **Font Size**: 11px

### 8. Dropdowns
- **Background**: White
- **Border**: 1px solid light gray
- **Shadow**: Medium shadow
- **Border Radius**: 8px
- **Item Padding**: 10px 16px
- **Hover**: Light blue background
- **Divider**: Light gray line

### 9. Tabs
- **Active Tab**: Primary Blue text with bottom border
- **Inactive Tab**: Medium gray text
- **Hover**: Primary Blue text
- **Padding**: 12px 20px
- **Border Bottom**: 2px solid

### 10. Alerts/Notifications
- **Success**: Light green background, dark green text, green icon
- **Warning**: Light yellow background, dark yellow text, yellow icon
- **Error**: Light red background, dark red text, red icon
- **Info**: Light blue background, dark blue text, blue icon
- **Border Radius**: 8px
- **Padding**: 16px
- **Dismiss Button**: Top right, icon only

## Layout Structure

### Responsive Breakpoints
- **Mobile**: < 576px
- **Tablet**: 576px - 992px
- **Desktop**: 992px - 1200px
- **Large Desktop**: > 1200px

### Grid System
- **Columns**: 12-column grid
- **Gutter**: 20px spacing between columns
- **Container**: Max width 1200px on desktop

### Page Layout
```
┌─────────────────────────────────────────┐
│  Top Navigation (60px)                  │
├────────┬────────────────────────────────┤
│        │                                │
│ Sidebar│  Main Content Area              │
│ (250px)│  - Cards (Grid layout)          │
│        │  - Charts                      │
│        │  - Tables                       │
│        │  - Forms                       │
│        │                                │
└────────┴────────────────────────────────┘
```

## Animations & Transitions

### Micro-interactions
- **Button Click**: Scale down to 0.95, then back to 1.0 (100ms)
- **Hover**: Scale up to 1.02 (200ms ease-out)
- **Card Load**: Fade in with slide up (300ms ease-out)
- **Dropdown**: Fade in with scale (200ms ease-out)
- **Tab Switch**: Fade content (200ms)

### Loading States
- **Skeleton Loading**: Gray placeholder bars with shimmer effect
- **Spinner**: Primary blue circular spinner
- **Progress Bar**: Primary blue with smooth animation

## Icons
- **Icon Set**: Use standard icon library (FontAwesome, Material Icons, or similar)
- **Size**: 16px (small), 20px (medium), 24px (large)
- **Color**: Inherit text color or semantic colors
- **Spacing**: 8px from adjacent text

## Accessibility
- **Contrast Ratio**: Minimum 4.5:1 for text, 3:1 for large text
- **Focus States**: Clear 2px outline in primary blue
- **Touch Targets**: Minimum 44x44px for mobile
- **Screen Reader**: Proper labels and descriptions
- **Keyboard Navigation**: Full keyboard support with visible focus

## MAUI-Specific Implementation

### XAML Structure
```xml
<ContentPage xmlns="http://schemas.microsoft.com/dotnet/2021/maui"
             xmlns:x="http://schemas.microsoft.com/winfx/2009/xaml"
             x:Class="AdminDashboard.MainPage">
    
    <!-- Main Grid Layout -->
    <Grid RowDefinitions="60, *" ColumnDefinitions="250, *">
        
        <!-- Top Navigation -->
        <Grid Grid.Row="0" Grid.ColumnSpan="2" BackgroundColor="White">
            <!-- Navigation content -->
        </Grid>
        
        <!-- Sidebar -->
        <Grid Grid.Row="1" Grid.Column="0" BackgroundColor="#1A1D2E">
            <!-- Sidebar content -->
        </Grid>
        
        <!-- Main Content -->
        <ScrollView Grid.Row="1" Grid.Column="1">
            <StackLayout Padding="20" Spacing="20">
                <!-- Cards, Charts, Tables -->
            </StackLayout>
        </ScrollView>
        
    </Grid>
</ContentPage>
```

### Custom Controls
1. **CardView**: Reusable card component with shadow and radius
2. **ChartView**: Integration with chart library (Microcharts, Syncfusion)
3. **BadgeView**: Pill-shaped badge component
4. **DropdownView**: Custom dropdown with animation
5. **TableView**: Styled table with hover effects

### Resource Dictionary
```xml
<ResourceDictionary>
    <!-- Colors -->
    <Color x:Key="PrimaryBlue">#2C7BE5</Color>
    <Color x:Key="SuccessGreen">#00D97E</Color>
    <Color x:Key="WarningYellow">#F6C343</Color>
    <Color x:Key="DangerRed">#E63757</Color>
    <Color x:Key="InfoLightBlue">#39ADF0</Color>
    
    <!-- Styles -->
    <Style TargetType="Button" x:Key="PrimaryButton">
        <Setter Property="BackgroundColor" Value="{StaticResource PrimaryBlue}"/>
        <Setter Property="TextColor" Value="White"/>
        <Setter Property="CornerRadius" Value="6"/>
        <Setter Property="Padding" Value="20,10"/>
    </Style>
    
    <Style TargetType="Frame" x:Key="CardStyle">
        <Setter Property="BackgroundColor" Value="White"/>
        <Setter Property="CornerRadius" Value="12"/>
        <Setter Property="HasShadow" Value="True"/>
        <Setter Property="Padding" Value="20"/>
    </Style>
</ResourceDictionary>
```

## Dashboard Page Specifics

### Statistics Cards
- **Layout**: Grid of 4 cards (2x2 on desktop, 1x4 on mobile)
- **Content**: Icon, label, value, percentage change
- **Color Coding**: Green for positive, red for negative
- **Animation**: Countup animation for numbers

### Chart Cards
- **Types**: Line chart, bar chart, pie chart, donut chart
- **Legend**: Bottom aligned
- **Tooltip**: Custom styled tooltip
- **Responsive**: Resize on orientation change

### Data Tables
- **Features**: Sort, filter, pagination
- **Actions**: View, edit, delete buttons per row
- **Selection**: Checkbox for bulk actions
- **Export**: CSV, Excel, PDF export options

### Navigation Menu Items
- Dashboard
- Analytics
- Reports
- Settings
- Users
- Support

## Performance Considerations
- **Lazy Loading**: Load charts and heavy content on demand
- **Virtualization**: For long lists and tables
- **Caching**: Cache API responses and images
- **Optimization**: Use compiled bindings, avoid unnecessary layouts

## Testing
- **Responsive Testing**: Test on mobile, tablet, desktop
- **Accessibility Testing**: Screen reader compatibility
- **Performance Testing**: Load time, memory usage
- **Cross-Platform**: iOS, Android, Windows, macOS

## Deliverables
1. MAUI project structure with proper MVVM architecture
2. Custom control library for reusable components
3. Resource dictionary with colors and styles
4. Sample dashboard page with all components
5. Responsive layout that works on all platforms
6. Clean, maintainable code with comments
7. Documentation for customization

## Key Design Principles
1. **Consistency**: Uniform spacing, colors, typography throughout
2. **Hierarchy**: Clear visual hierarchy with size and color
3. **Simplicity**: Clean, uncluttered interface
4. **Accessibility**: WCAG AA compliant
5. **Performance**: Smooth animations, fast loading
6. **Responsiveness**: Optimized for all screen sizes
7. **Professional**: Enterprise-grade appearance
