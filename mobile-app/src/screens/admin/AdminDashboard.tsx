"use client"

import { useState, useEffect } from "react"
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, RefreshControl, Alert } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

const API_URL = "https://schoolweb.ct.ws"

export default function AdminDashboard() {
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

      const response = await axios.get(`${API_URL}/api/admin/dashboard.php?user_id=${userId}`, {
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
            <Text style={styles.greeting}>Welcome Admin,</Text>
            <Text style={styles.name}>{userData.full_name}</Text>
          </View>

          <View style={styles.statsContainer}>
            {stats && (
              <>
                <View style={styles.statCard}>
                  <MaterialIcons name="people" size={32} color="#0066cc" />
                  <Text style={styles.statValue}>{stats.total_students || 0}</Text>
                  <Text style={styles.statLabel}>Students</Text>
                </View>

                <View style={styles.statCard}>
                  <MaterialIcons name="school" size={32} color="#28a745" />
                  <Text style={styles.statValue}>{stats.total_teachers || 0}</Text>
                  <Text style={styles.statLabel}>Teachers</Text>
                </View>

                <View style={styles.statCard}>
                  <MaterialIcons name="assignment" size={32} color="#ffc107" />
                  <Text style={styles.statValue}>{stats.total_exams || 0}</Text>
                  <Text style={styles.statLabel}>Exams</Text>
                </View>

                <View style={styles.statCard}>
                  <MaterialIcons name="receipt" size={32} color="#17a2b8" />
                  <Text style={styles.statValue}>${stats.total_fees || 0}</Text>
                  <Text style={styles.statLabel}>Total Fees</Text>
                </View>
              </>
            )}
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
})
