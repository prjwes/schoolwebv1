"use client"

import { useState } from "react"
import { View, Text, ScrollView, StyleSheet, Switch } from "react-native"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

export default function Settings() {
  const [notifications, setNotifications] = useState(true)
  const [darkMode, setDarkMode] = useState(false)
  const [autoSync, setAutoSync] = useState(true)

  return (
    <ScrollView style={styles.container}>
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Preferences</Text>

        <View style={styles.settingItem}>
          <View style={styles.settingInfo}>
            <MaterialIcons name="notifications" size={24} color="#0066cc" />
            <View style={styles.settingText}>
              <Text style={styles.settingLabel}>Push Notifications</Text>
              <Text style={styles.settingDesc}>Receive exam and class notifications</Text>
            </View>
          </View>
          <Switch value={notifications} onValueChange={setNotifications} />
        </View>

        <View style={styles.settingItem}>
          <View style={styles.settingInfo}>
            <MaterialIcons name="dark-mode" size={24} color="#0066cc" />
            <View style={styles.settingText}>
              <Text style={styles.settingLabel}>Dark Mode</Text>
              <Text style={styles.settingDesc}>Enable dark theme</Text>
            </View>
          </View>
          <Switch value={darkMode} onValueChange={setDarkMode} />
        </View>

        <View style={styles.settingItem}>
          <View style={styles.settingInfo}>
            <MaterialIcons name="sync" size={24} color="#0066cc" />
            <View style={styles.settingText}>
              <Text style={styles.settingLabel}>Auto-sync</Text>
              <Text style={styles.settingDesc}>Automatically sync data</Text>
            </View>
          </View>
          <Switch value={autoSync} onValueChange={setAutoSync} />
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>About</Text>

        <View style={styles.aboutItem}>
          <Text style={styles.aboutLabel}>School Management System v22</Text>
          <Text style={styles.aboutValue}>Mobile App 1.0.0</Text>
        </View>

        <View style={styles.aboutItem}>
          <Text style={styles.aboutLabel}>Developed by</Text>
          <Text style={styles.aboutValue}>v0 AI</Text>
        </View>
      </View>
    </ScrollView>
  )
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  section: {
    paddingVertical: 15,
    paddingHorizontal: 15,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#333",
    marginBottom: 12,
  },
  settingItem: {
    backgroundColor: "#fff",
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 15,
    paddingVertical: 12,
    marginBottom: 8,
    borderRadius: 8,
  },
  settingInfo: {
    flexDirection: "row",
    alignItems: "center",
    flex: 1,
  },
  settingText: {
    marginLeft: 15,
    flex: 1,
  },
  settingLabel: {
    fontSize: 14,
    fontWeight: "600",
    color: "#333",
  },
  settingDesc: {
    fontSize: 12,
    color: "#999",
    marginTop: 4,
  },
  aboutItem: {
    backgroundColor: "#fff",
    paddingHorizontal: 15,
    paddingVertical: 12,
    marginBottom: 8,
    borderRadius: 8,
  },
  aboutLabel: {
    fontSize: 14,
    color: "#666",
  },
  aboutValue: {
    fontSize: 14,
    fontWeight: "600",
    color: "#333",
    marginTop: 4,
  },
})
