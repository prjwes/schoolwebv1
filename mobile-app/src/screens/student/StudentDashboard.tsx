"use client"

import { useState, useEffect } from "react"
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, RefreshControl, Alert } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

const API_URL = "https://schoolweb.ct.ws"

export default function StudentDashboard() {
  const [userData, setUserData] = useState(null)
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  useEffect(() => {
    fetchDashboardData()
  }, [])

  const fetchDashboardData = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")

      const response = await axios.get(`${API_URL}/api/student/dashboard.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setUserData(response.data.user)
        setStats(response.data.stats)
      }
    } catch (error) {
      console.error("Error fetching dashboard:", error)
      Alert.alert("Error", "Failed to load dashboard data")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  const onRefresh = () => {
    setRefreshing(true)
    fetchDashboardData()
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
    >
      {userData && (
        <>
          <View style={styles.header}>
            <Text style={styles.greeting}>Welcome back,</Text>
            <Text style={styles.name}>{userData.full_name}</Text>
          </View>

          <View style={styles.statsContainer}>
            {stats && (
              <>
                <View style={styles.statCard}>
                  <MaterialIcons name="assignment" size={32} color="#0066cc" />
                  <Text style={styles.statValue}>{stats.exams_completed || 0}</Text>
                  <Text style={styles.statLabel}>Exams Completed</Text>
                </View>

                <View style={styles.statCard}>
                  <MaterialIcons name="schedule" size={32} color="#28a745" />
                  <Text style={styles.statValue}>{stats.classes_today || 0}</Text>
                  <Text style={styles.statLabel}>Classes Today</Text>
                </View>

                <View style={styles.statCard}>
                  <MaterialIcons name="receipt" size={32} color="#ffc107" />
                  <Text style={styles.statValue}>${stats.pending_fees || 0}</Text>
                  <Text style={styles.statLabel}>Pending Fees</Text>
                </View>

                <View style={styles.statCard}>
                  <MaterialIcons name="people" size={32} color="#17a2b8" />
                  <Text style={styles.statValue}>{stats.clubs_joined || 0}</Text>
                  <Text style={styles.statLabel}>Clubs Joined</Text>
                </View>
              </>
            )}
          </View>

          <View style={styles.infoSection}>
            <Text style={styles.sectionTitle}>Academic Info</Text>
            <View style={styles.infoCard}>
              <View style={styles.infoRow}>
                <Text style={styles.label}>Grade:</Text>
                <Text style={styles.value}>{userData.grade || "N/A"}</Text>
              </View>
              <View style={styles.infoRow}>
                <Text style={styles.label}>Admission #:</Text>
                <Text style={styles.value}>{userData.admission_number || "N/A"}</Text>
              </View>
              <View style={styles.infoRow}>
                <Text style={styles.label}>Status:</Text>
                <Text style={[styles.value, { color: "#28a745" }]}>Active</Text>
              </View>
            </View>
          </View>
        </>
      )}
    </ScrollView>
  )
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  centerContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  header: {
    backgroundColor: "#0066cc",
    paddingHorizontal: 20,
    paddingVertical: 25,
  },
  greeting: {
    fontSize: 16,
    color: "#e0e0e0",
    marginBottom: 5,
  },
  name: {
    fontSize: 28,
    fontWeight: "bold",
    color: "#fff",
  },
  statsContainer: {
    flexDirection: "row",
    flexWrap: "wrap",
    padding: 10,
  },
  statCard: {
    width: "50%",
    padding: 10,
  },
  statCardContent: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 15,
    alignItems: "center",
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
  },
  statValue: {
    fontSize: 24,
    fontWeight: "bold",
    color: "#333",
    marginVertical: 8,
  },
  statLabel: {
    fontSize: 12,
    color: "#666",
    textAlign: "center",
  },
  infoSection: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: "600",
    color: "#333",
    marginBottom: 12,
  },
  infoCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 15,
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
  },
  infoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#eee",
  },
  label: {
    fontSize: 14,
    color: "#666",
  },
  value: {
    fontSize: 14,
    fontWeight: "600",
    color: "#333",
  },
})
