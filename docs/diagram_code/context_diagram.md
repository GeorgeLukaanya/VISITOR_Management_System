/**
eraser.io 
'
version 1 

DATE 24/06/2024 
**/

title Context Diagram (C4)
// --------------------------------------------------------
// Node Definitions (Actors & Core Components)
// --------------------------------------------------------

User [shape: person, icon: user, color: blue] {
  User / Visitor
  "Initiates registration or check-in"
}

USSD_Gateway [shape: cloud, color: gray] {
  USSD Service Provider
  "Processes shortcode sessions (*123#)"
}

VMS [shape: rectangle, color: blue, style: filled] {
  Visitor Management System
  "Handles system logic, check-ins, notifications, and logs"
}

Building manager [shape: person, icon: user, color: blue] {
  building manager
  can see the building and handle exports
}

Admin [shape: person, icon: user, color: blue] {
  Admin / Management
  "Manages system settings and views analytics"
}

// --------------------------------------------------------
// Relationship / Flow Definitions
// --------------------------------------------------------

// USSD User Journey
User > USSD_Gateway: "Dials USSD Shortcode"
USSD_Gateway > VMS: "Relays session data / visitor requests"
VMS > USSD_Gateway: "Sends menu options back to User"

Building manager > VMS

// Alternative Web/App Journey (If applicable)

// VMS Internal Processing Logic
VMS > Notification_Box: "Triggers alert"
Notification_Box [shape: page] {
  Notification Engine
  "Sends SMS, email, or push notifications"
}

// Internal Roles Workflow
Admin > VMS: "Configures Policies & Settings"