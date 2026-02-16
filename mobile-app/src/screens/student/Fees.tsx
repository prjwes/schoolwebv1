"use client"

import { useState, useEffect } from "react"
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, RefreshControl, Alert } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"

const API_URL = "https://schoolweb.ct.ws"

export default function Fees() {
  const [fees, setFees] = useState(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  useEffect(() => {
    fetchFees()
  }, [])

  const fetchFees = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")

      const response = await axios.get(`${API_URL}/api/student/fees.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setFees(response.data.fees)
      }
    } catch (error) {
      console.error("Error fetching fees:", error)
      Alert.alert("Error", "Failed to load fees")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  const onRefresh = () => {
    setRefreshing(true)
    fetchFees()
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
      {fees ? (
        <View style={styles.content}>
          <View style={styles.summaryCard}>
            <View style={styles.summaryRow}>
              <Text style={styles.label}>Total Fees:</Text>
              <Text style={styles.amount}>${fees.total || 0}</Text>
            </View>
            <View style={styles.summaryRow}>
              <Text style={styles.label}>Paid:</Text>
              <Text style={[styles.amount, { color: "#28a745" }]}>${fees.paid || 0}</Text>
            </View>
            <View style={styles.summaryRow}>
              <Text style={styles.label}>Balance:</Text>
              <Text style={[styles.amount, { color: "#dc3545" }]}>${fees.balance || 0}</Text>
            </View>
          </View>

          {fees.transactions && fees.transactions.length > 0 && (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Payment History</Text>
              {fees.transactions.map((txn, idx) => (
                <View key={idx} style={styles.transactionCard}>
                  <View style={styles.transactionInfo}>
                    <Text style={styles.transactionDate}>{txn.date}</Text>
                    <Text style={styles.transactionDesc}>{txn.description}</Text>
                  </View>
                  <Text style={[styles.transactionAmount, { color: "#28a745" }]}>+${txn.amount}</Text>
                </View>
              ))}
            </View>
          )}
        </View>
      ) : (
        <View style={styles.emptyContainer}>
          <Text style={styles.emptyText}>No fees data available</Text>
        </View>
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
  content: {
    padding: 15,
  },
  summaryCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 20,
    marginBottom: 20,
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
  },
  summaryRow: {
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
  amount: {
    fontSize: 16,
    fontWeight: "600",
    color: "#333",
  },
  section: {
    marginTop: 10,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#333",
    marginBottom: 12,
  },
  transactionCard: {
    backgroundColor: "#fff",
    borderRadius: 8,
    padding: 12,
    marginBottom: 10,
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    shadowColor: "#000",
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 2,
  },
  transactionInfo: {
    flex: 1,
  },
  transactionDate: {
    fontSize: 12,
    color: "#999",
  },
  transactionDesc: {
    fontSize: 13,
    color: "#333",
    marginTop: 4,
  },
  transactionAmount: {
    fontSize: 14,
    fontWeight: "600",
  },
  emptyContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingTop: 100,
  },
  emptyText: {
    fontSize: 16,
    color: "#999",
  },
})
